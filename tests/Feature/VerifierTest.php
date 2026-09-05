<?php

declare(strict_types=1);

use HansDeBoeck\Turnstile\Facades\Turnstile;
use HansDeBoeck\Turnstile\TurnstileResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('laat een geldig token door', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyOk())]);

    $result = Turnstile::verify('een-token');

    expect($result->passed)->toBeTrue()
        ->and($result->skipped)->toBeFalse()
        ->and($result->hostname)->toBe('kringwinkel.be');
});

it('weigert een token dat Cloudflare afwijst', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyFout())]);

    $result = Turnstile::verify('een-token');

    expect($result->passed)->toBeFalse()
        ->and($result->failure)->toBe(TurnstileResult::FAILURE_REJECTED)
        ->and($result->errorCodes)->toBe(['invalid-input-response']);
});

it('stuurt de geheime sleutel en het token mee', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyOk())]);

    Turnstile::verify('een-token', '10.0.0.1');

    Http::assertSent(function ($request): bool {
        return $request['secret'] === 'test-secret'
            && $request['response'] === 'een-token'
            && $request['remoteip'] === '10.0.0.1';
    });
});

it('probeert het nooit opnieuw, want een token is eenmalig', function (): void {
    $pogingen = 0;

    Http::fake(['challenges.cloudflare.com/*' => function () use (&$pogingen) {
        $pogingen++;

        throw new ConnectionException('time-out');
    }]);

    $result = Turnstile::verify('een-token');

    expect($result->passed)->toBeFalse()
        ->and($result->failure)->toBe(TurnstileResult::FAILURE_HTTP_ERROR)
        ->and($pogingen)->toBe(1);
});

it('weigert wanneer Cloudflare een foutstatus geeft', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response('kapot', 502)]);

    expect(Turnstile::verify('een-token')->failure)->toBe(TurnstileResult::FAILURE_HTTP_ERROR);
});

it('laat door bij een storing zodra fail_open aanstaat', function (): void {
    config()->set('turnstile.fail_open', true);
    Http::fake(['challenges.cloudflare.com/*' => fn () => throw new ConnectionException('time-out')]);

    $result = Turnstile::verify('een-token');

    expect($result->passed)->toBeTrue()
        ->and($result->skipped)->toBeTrue();
});

it('bevraagt Cloudflare niet voor een leeg token', function (): void {
    Http::fake();

    expect(Turnstile::verify(null)->failure)->toBe(TurnstileResult::FAILURE_MISSING_TOKEN)
        ->and(Turnstile::verify('   ')->failure)->toBe(TurnstileResult::FAILURE_MISSING_TOKEN);

    Http::assertNothingSent();
});

it('stuurt geen ip mee wanneer remote_ip op none staat', function (): void {
    config()->set('turnstile.remote_ip', 'none');
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyOk())]);

    Turnstile::verify('een-token');

    Http::assertSent(fn ($request): bool => ! array_key_exists('remoteip', $request->data()));
});

it('laat de Cloudflare-header voorgaan wanneer dat zo ingesteld staat', function (): void {
    config()->set('turnstile.remote_ip', 'cloudflare');
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyOk())]);

    $this->app['request']->headers->set('CF-Connecting-IP', '203.0.113.7');

    Turnstile::verify('een-token');

    Http::assertSent(fn ($request): bool => $request['remoteip'] === '203.0.113.7');
});

it('gebruikt het eigen verify-eindpunt uit de config', function (): void {
    config()->set('turnstile.verify_url', 'https://proxy.test/siteverify');
    Http::fake(['proxy.test/*' => Http::response(siteverifyOk())]);

    Turnstile::verify('een-token');

    Http::assertSent(fn ($request): bool => $request->url() === 'https://proxy.test/siteverify');
});

it('geeft passes() terug als kale boolean', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyOk())]);

    expect(Turnstile::passes('een-token'))->toBeTrue();
});
