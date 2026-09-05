<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile\Facades;

use HansDeBoeck\Turnstile\Contracts\VerifiesTurnstile;
use HansDeBoeck\Turnstile\TurnstileFake;
use HansDeBoeck\Turnstile\TurnstileResult;
use HansDeBoeck\Turnstile\TurnstileVerifier;
use Illuminate\Support\Facades\Facade;

/**
 * @method static TurnstileResult verify(?string $token, ?string $ip = null)
 * @method static bool passes(?string $token, ?string $ip = null)
 * @method static bool configured()
 * @method static string|null siteKey()
 * @method static array<string, mixed> widgetDefaults()
 *
 * @see TurnstileVerifier
 */
class Turnstile extends Facade
{
    /**
     * Laat elke controle slagen, of laat een callable per token beslissen.
     *
     * @param  bool|callable(string|null): bool  $outcome
     */
    public static function fake(bool|callable $outcome = true): TurnstileFake
    {
        return static::swapFake(new TurnstileFake(
            static::getFacadeApplication(),
            $outcome,
        ));
    }

    /** Laat elke controle falen, zodat de foutafhandeling getest kan worden. */
    public static function fakeFailure(string $failure = TurnstileResult::FAILURE_REJECTED): TurnstileFake
    {
        return static::swapFake(new TurnstileFake(
            static::getFacadeApplication(),
            false,
            $failure,
        ));
    }

    /** Doet alsof er geen sleutels ingesteld staan. */
    public static function fakeUnconfigured(): TurnstileFake
    {
        return static::swapFake(new TurnstileFake(
            static::getFacadeApplication(),
            true,
            TurnstileResult::FAILURE_NOT_CONFIGURED,
            false,
        ));
    }

    /**
     * Drie bindingen, niet alleen swap(): de validatieregel resolvet het
     * contract, code die de concrete klasse type-hint resolvet die, en de facade
     * zelf gaat via haar eigen accessor. Zonder alle drie werkt fake() maar in
     * een deel van de gevallen.
     */
    protected static function swapFake(TurnstileFake $fake): TurnstileFake
    {
        static::swap($fake);

        $app = static::getFacadeApplication();
        $app->instance(VerifiesTurnstile::class, $fake);
        $app->instance(TurnstileVerifier::class, $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return TurnstileVerifier::class;
    }
}
