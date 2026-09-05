<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile;

use HansDeBoeck\Turnstile\Contracts\VerifiesTurnstile;
use HansDeBoeck\Turnstile\Rules\Turnstile as TurnstileRule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class TurnstileServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/turnstile.php', 'turnstile');

        $this->app->singleton(TurnstileVerifier::class, function ($app): TurnstileVerifier {
            return new TurnstileVerifier(
                (array) $app['config']->get('turnstile', []),
                $app,
            );
        });

        $this->app->alias(TurnstileVerifier::class, VerifiesTurnstile::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'turnstile');
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'turnstile');

        // Enkel met prefix: <x-turnstile::stage>. Een prefixloze registratie zou
        // namen als <x-field> in de gedeelde ruimte claimen.
        Blade::anonymousComponentPath(__DIR__.'/../resources/views/components', 'turnstile');

        $this->registerValidationRule();

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
        }
    }

    /**
     * Lui geregistreerd: anders resolvet elke request de validation factory
     * alleen omdat deze provider boot.
     */
    protected function registerValidationRule(): void
    {
        $this->callAfterResolving('validator', function ($factory): void {
            $factory->extend(
                'turnstile',
                function (string $attribute, mixed $value): bool {
                    // Het contract per aanroep resolven, niet een instantie
                    // vastpinnen: anders ziet de regel Turnstile::fake() niet.
                    return is_string($value)
                        && trim($value) !== ''
                        && $this->app->make(VerifiesTurnstile::class)->passes($value);
                },
                TurnstileRule::defaultMessage(),
            );
        });
    }

    protected function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/turnstile.php' => $this->app->configPath('turnstile.php'),
        ], 'turnstile-config');

        $this->publishes([
            __DIR__.'/../dist' => $this->app->publicPath('vendor/turnstile'),
        ], 'turnstile-assets');

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/turnstile'),
        ], 'turnstile-views');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/turnstile'),
        ], 'turnstile-lang');
    }
}
