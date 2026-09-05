<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile;

use Illuminate\Container\Container;

/**
 * URL's voor de bestanden die `vendor:publish --tag=turnstile-assets` neerzet.
 *
 * Applicaties met een eigen asset-helper (cachebusting, .min-omschakeling)
 * zetten die in `turnstile.assets.resolver`; anders geldt de ingebouwde
 * filemtime-versionering hieronder.
 *
 * url() levert een reeds ge-escapete URL, klaar om rechtstreeks in een
 * attribuut te echoen. De Blade-componenten gebruiken daarom {!! !!}. Een eigen
 * resolver hoort zich aan dezelfde afspraak te houden.
 */
final class TurnstileAssets
{
    public static function url(string $file): string
    {
        $container = Container::getInstance();
        $config = $container->make('config');

        $path = trim((string) $config->get('turnstile.assets.path', 'vendor/turnstile'), '/')
            .'/'.ltrim($file, '/');

        if ($resolver = $config->get('turnstile.assets.resolver')) {
            return (string) $container->call($resolver, ['path' => $path]);
        }

        return self::versioned($path);
    }

    protected static function versioned(string $path): string
    {
        $public = self::publicPath();

        // In productie de .min-variant, maar alleen wanneer die er echt staat.
        if ($public !== null && ! str_contains($path, '.min.')) {
            $minified = preg_replace('/\.(js|css)$/', '.min.$1', $path);

            if (is_string($minified) && is_file($public.'/'.$minified)) {
                $path = $minified;
            }
        }

        $url = '/'.$path;

        if ($public !== null && is_file($public.'/'.$path)) {
            $url .= '?v='.filemtime($public.'/'.$path);
        }

        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8', false);
    }

    protected static function publicPath(): ?string
    {
        $container = Container::getInstance();

        return method_exists($container, 'publicPath') ? $container->publicPath() : null;
    }
}
