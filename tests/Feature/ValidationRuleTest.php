<?php

declare(strict_types=1);

use HansDeBoeck\Turnstile\Facades\Turnstile;
use HansDeBoeck\Turnstile\Rules\Turnstile as TurnstileRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

it('laat de stringregel slagen bij een geldig token', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyOk())]);

    $validator = Validator::make(
        ['turnstile_token' => 'een-token'],
        ['turnstile_token' => 'required|string|turnstile'],
    );

    expect($validator->passes())->toBeTrue();
});

it('laat de stringregel falen bij een afgewezen token', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyFout())]);

    $validator = Validator::make(
        ['turnstile_token' => 'een-token'],
        ['turnstile_token' => 'required|string|turnstile'],
    );

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('turnstile_token'))->not->toBe('');
});

it('bevraagt Cloudflare niet voor een leeg veld', function (): void {
    Http::fake();

    $validator = Validator::make(
        ['turnstile_token' => ''],
        ['turnstile_token' => 'required|string|turnstile'],
    );

    expect($validator->fails())->toBeTrue();

    Http::assertNothingSent();
});

it('werkt ook als objectregel', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyFout())]);

    $validator = Validator::make(
        ['turnstile_token' => 'een-token'],
        ['turnstile_token' => ['required', new TurnstileRule]],
    );

    expect($validator->fails())->toBeTrue();
});

it('neemt een eigen boodschap over', function (): void {
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyFout())]);

    $validator = Validator::make(
        ['turnstile_token' => 'een-token'],
        ['turnstile_token' => ['required', new TurnstileRule(message: 'Eigen tekst.')]],
    );

    expect($validator->errors()->first('turnstile_token'))->toBe('Eigen tekst.');
});

it('laat de applicatie de boodschap overschrijven via validation.turnstile', function (): void {
    app('translator')->addLines(['validation.turnstile' => 'Onze eigen tekst.'], 'en');
    Http::fake(['challenges.cloudflare.com/*' => Http::response(siteverifyFout())]);

    $validator = Validator::make(
        ['turnstile_token' => 'een-token'],
        ['turnstile_token' => 'required|turnstile'],
    );

    expect($validator->errors()->first('turnstile_token'))->toBe('Onze eigen tekst.');
});

it('gebruikt de fake ook binnen de validatieregel', function (): void {
    $fake = Turnstile::fakeFailure();

    $validator = Validator::make(
        ['turnstile_token' => 'een-token'],
        ['turnstile_token' => 'required|turnstile'],
    );

    expect($validator->fails())->toBeTrue();
    $fake->assertVerified('een-token');
});
