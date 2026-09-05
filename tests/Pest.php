<?php

declare(strict_types=1);

use HansDeBoeck\Turnstile\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * De vorm van een geslaagd siteverify-antwoord.
 *
 * @param  array<string, mixed>  $extra
 * @return array<string, mixed>
 */
function siteverifyOk(array $extra = []): array
{
    return array_merge([
        'success' => true,
        'challenge_ts' => '2026-09-05T10:00:00.000Z',
        'hostname' => 'kringwinkel.be',
    ], $extra);
}

/**
 * @param  list<string>  $codes
 * @return array<string, mixed>
 */
function siteverifyFout(array $codes = ['invalid-input-response']): array
{
    return ['success' => false, 'error-codes' => $codes];
}
