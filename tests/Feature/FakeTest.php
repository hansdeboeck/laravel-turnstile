<?php

declare(strict_types=1);

use HansDeBoeck\Turnstile\Contracts\VerifiesTurnstile;
use HansDeBoeck\Turnstile\Facades\Turnstile;
use HansDeBoeck\Turnstile\TurnstileFake;
use HansDeBoeck\Turnstile\TurnstileResult;
use HansDeBoeck\Turnstile\TurnstileVerifier;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('laat elke controle slagen zonder netwerkverkeer', function (): void {
    $fake = Turnstile::fake();

    expect(Turnstile::passes('wat-dan-ook'))->toBeTrue();
    $fake->assertVerified('wat-dan-ook');
    $fake->assertVerifiedCount(1);
});

it('vervangt ook het contract en de concrete klasse in de container', function (): void {
    Turnstile::fake();

    expect(app(VerifiesTurnstile::class))->toBeInstanceOf(TurnstileFake::class)
        ->and(app(TurnstileVerifier::class))->toBeInstanceOf(TurnstileFake::class);
});

it('laat een callable per token beslissen', function (): void {
    Turnstile::fake(fn (?string $token): bool => $token === 'goed');

    expect(Turnstile::passes('goed'))->toBeTrue()
        ->and(Turnstile::passes('fout'))->toBeFalse();
});

it('kan elke controle laten falen', function (): void {
    Turnstile::fakeFailure();

    expect(Turnstile::verify('een-token')->failure)->toBe(TurnstileResult::FAILURE_REJECTED);
});

it('kan doen alsof er geen sleutels ingesteld staan', function (): void {
    Turnstile::fakeUnconfigured();

    expect(Turnstile::configured())->toBeFalse()
        ->and(Turnstile::siteKey())->toBeNull()
        ->and(Turnstile::verify('een-token')->failure)->toBe(TurnstileResult::FAILURE_NOT_CONFIGURED);
});

it('bewijst dat er niets gecontroleerd is', function (): void {
    Turnstile::fake()->assertNothingVerified();
});
