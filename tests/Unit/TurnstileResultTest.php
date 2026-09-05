<?php

declare(strict_types=1);

use HansDeBoeck\Turnstile\TurnstileResult;

it('geeft de foutcodes van Cloudflare nooit mee in een respons', function (): void {
    $result = TurnstileResult::failed(TurnstileResult::FAILURE_REJECTED, ['invalid-input-secret']);

    expect($result->toArray())->toBe([
        'passed' => false,
        'skipped' => false,
        'failure' => 'rejected',
    ]);

    expect(json_encode($result))->not->toContain('invalid-input-secret');
});

it('houdt de foutcodes wel beschikbaar voor het logboek', function (): void {
    $result = TurnstileResult::failed(TurnstileResult::FAILURE_REJECTED, ['invalid-input-secret']);

    expect($result->forLogging()['error_codes'])->toBe(['invalid-input-secret']);
});

it('leest de velden uit een geslaagd antwoord', function (): void {
    $result = TurnstileResult::success(siteverifyOk(['action' => 'contact']));

    expect($result->passed)->toBeTrue()
        ->and($result->skipped)->toBeFalse()
        ->and($result->hostname)->toBe('kringwinkel.be')
        ->and($result->action)->toBe('contact')
        ->and($result->challengeTs)->toBe('2026-09-05T10:00:00.000Z');
});

it('markeert een overgeslagen controle als geslaagd', function (): void {
    $result = TurnstileResult::skipped();

    expect($result->passed)->toBeTrue()
        ->and($result->skipped)->toBeTrue()
        ->and($result->failure)->toBeNull();
});

it('herkent een afgewezen token', function (): void {
    expect(TurnstileResult::failed(TurnstileResult::FAILURE_REJECTED)->rejected())->toBeTrue()
        ->and(TurnstileResult::failed(TurnstileResult::FAILURE_HTTP_ERROR)->rejected())->toBeFalse();
});
