<?php

declare(strict_types=1);

use HansDeBoeck\Turnstile\Facades\Turnstile;
use HansDeBoeck\Turnstile\TurnstileResult;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake();
});

it('slaat de controle over in local wanneer de sleutel ontbreekt', function (): void {
    config()->set('turnstile.secret', null);
    $this->app['env'] = 'local';

    $result = Turnstile::verify('een-token');

    expect($result->passed)->toBeTrue()
        ->and($result->skipped)->toBeTrue();

    Http::assertNothingSent();
});

it('weigert in productie wanneer de sleutel ontbreekt', function (): void {
    config()->set('turnstile.secret', null);
    $this->app['env'] = 'production';

    expect(Turnstile::verify('een-token')->failure)->toBe(TurnstileResult::FAILURE_NOT_CONFIGURED);

    Http::assertNothingSent();
});

it('behandelt de sentinel NOTENV als een ontbrekende sleutel', function (): void {
    config()->set('turnstile.secret', 'NOTENV');
    config()->set('turnstile.key', 'NOTENV');
    $this->app['env'] = 'production';

    expect(Turnstile::configured())->toBeFalse()
        ->and(Turnstile::siteKey())->toBeNull()
        ->and(Turnstile::verify('een-token')->failure)->toBe(TurnstileResult::FAILURE_NOT_CONFIGURED);
});

it('verifieert wel echt in local zodra er een sleutel staat', function (): void {
    $this->app['env'] = 'local';
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyOk())]);

    expect(Turnstile::verify('een-token')->skipped)->toBeFalse();

    Http::assertSentCount(1);
});

it('valt terug op local en testing wanneer bypass_environments ontbreekt', function (): void {
    config()->set('turnstile.secret', null);
    config()->set('turnstile.bypass_environments', null);
    $this->app['env'] = 'testing';

    expect(Turnstile::verify('een-token')->skipped)->toBeTrue();
});

it('leest de sitekey uit de config', function (): void {
    expect(Turnstile::siteKey())->toBe('test-sitekey')
        ->and(Turnstile::configured())->toBeTrue();
});
