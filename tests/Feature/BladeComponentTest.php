<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('rendert het verborgen veld met het afgesproken data-attribuut', function (): void {
    $html = Blade::render('<x-turnstile::field />');

    expect($html)->toContain('name="turnstile_token"')
        ->and($html)->toContain('data-turnstile-field')
        ->and($html)->toContain('type="hidden"');
});

it('neemt een eigen veldnaam over', function (): void {
    expect(Blade::render('<x-turnstile::field name="botcheck" />'))->toContain('name="botcheck"');
});

it('rendert het vlak met de aria-bedrading', function (): void {
    $html = Blade::render('<x-turnstile::stage fixed for="retour" labelledby="titel">inhoud</x-turnstile::stage>');

    expect($html)->toContain('data-turnstile-stage="idle"')
        ->and($html)->toContain('data-turnstile-fixed')
        ->and($html)->toContain('data-turnstile-for="retour"')
        ->and($html)->toContain('aria-labelledby="titel"')
        ->and($html)->toContain('role="dialog"')
        ->and($html)->toContain('inhoud');
});

it('laat de klassen van de applicatie ongemoeid door', function (): void {
    $html = Blade::render('<x-turnstile::stage class="mijn-eigen-klasse" />');

    expect($html)->toContain('class="mijn-eigen-klasse"');
});

it('zet de sitekey op de container', function (): void {
    expect(Blade::render('<x-turnstile::container />'))
        ->toContain('data-sitekey="test-sitekey"')
        ->toContain('data-turnstile-container');
});

it('rendert niets zonder sitekey', function (): void {
    config()->set('turnstile.key', null);

    expect(trim(Blade::render('<x-turnstile::stage>inhoud</x-turnstile::stage>')))->toBe('')
        ->and(trim(Blade::render('<x-turnstile::container />')))->toBe('')
        ->and(trim(Blade::render('<x-turnstile::scripts />')))->toBe('')
        ->and(trim(Blade::render('<x-turnstile::widget />')))->toBe('');
});

it('escapet een sitekey die uit de config komt', function (): void {
    config()->set('turnstile.key', '"><script>alert(1)</script>');

    expect(Blade::render('<x-turnstile::container />'))->not->toContain('<script>alert(1)');
});

it('laadt api.js zonder onload-parameter', function (): void {
    $html = Blade::render('<x-turnstile::scripts />');

    expect($html)->toContain('turnstile/v0/api.js?render=explicit')
        ->and($html)->not->toContain('onload=')
        ->and($html)->toContain('vendor/turnstile/turnstile.js')
        ->and($html)->toContain('vendor/turnstile/turnstile.css');
});

it('kan api.js en de css overslaan', function (): void {
    $html = Blade::render('<x-turnstile::scripts :api="false" :css="false" />');

    expect($html)->not->toContain('api.js')
        ->and($html)->not->toContain('turnstile.css')
        ->and($html)->toContain('turnstile.js');
});

it('rendert de klassieke variant met callbacks', function (): void {
    $html = Blade::render('<x-turnstile::widget callback="opGeslaagd" theme="light" />');

    expect($html)->toContain('class="cf-turnstile"')
        ->and($html)->toContain('data-callback="opGeslaagd"')
        ->and($html)->toContain('data-theme="light"');
});

it('rendert de annuleerknop met het afgesproken attribuut', function (): void {
    $html = Blade::render('<x-turnstile::cancel class="knop">Annuleren</x-turnstile::cancel>');

    expect($html)->toContain('data-turnstile-cancel')
        ->and($html)->toContain('type="button"')
        ->and($html)->toContain('Annuleren');
});

it('rendert de brugpagina voor een WebView', function (): void {
    $html = view('turnstile::bridge')->render();

    expect($html)->toContain('data-turnstile-bridge')
        ->and($html)->toContain('turnstile-bridge.js')
        ->and($html)->toContain('data-sitekey="test-sitekey"')
        ->and($html)->toContain('noindex')
        // De brug mag geen inline script bevatten: die zou een CSP-uitzondering
        // vragen die de applicatie net probeert af te bouwen.
        ->and($html)->not->toMatch('/<script(?![^>]*\ssrc=)[^>]*>/');
});
