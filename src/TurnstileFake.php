<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile;

use Illuminate\Contracts\Foundation\Application;
use PHPUnit\Framework\Assert;

/**
 * Vervangt de verifier in tests. Erft van TurnstileVerifier zodat hij ook past
 * waar code de concrete klasse type-hint.
 */
class TurnstileFake extends TurnstileVerifier
{
    /** @var list<array{token: string|null, ip: string|null}> */
    public array $verifications = [];

    /**
     * @param  bool|callable(string|null): bool  $outcome
     */
    public function __construct(
        Application $app,
        protected mixed $outcome = true,
        protected string $failureReason = TurnstileResult::FAILURE_REJECTED,
        protected bool $isConfigured = true,
        protected ?string $fakeSiteKey = 'test-sitekey',
    ) {
        parent::__construct([], $app);
    }

    public function verify(?string $token, ?string $ip = null): TurnstileResult
    {
        $this->verifications[] = ['token' => $token, 'ip' => $ip];

        if (! $this->isConfigured) {
            return TurnstileResult::failed(TurnstileResult::FAILURE_NOT_CONFIGURED);
        }

        $passed = is_callable($this->outcome)
            ? (bool) ($this->outcome)($token)
            : (bool) $this->outcome;

        return $passed
            ? TurnstileResult::success()
            : TurnstileResult::failed($this->failureReason);
    }

    public function configured(): bool
    {
        return $this->isConfigured;
    }

    public function siteKey(): ?string
    {
        return $this->isConfigured ? $this->fakeSiteKey : null;
    }

    public function assertVerified(?string $token = null): void
    {
        if ($token === null) {
            Assert::assertNotEmpty($this->verifications, 'Er is geen enkel Turnstile-token gecontroleerd.');

            return;
        }

        Assert::assertContains(
            $token,
            array_column($this->verifications, 'token'),
            "Het token [{$token}] is niet gecontroleerd.",
        );
    }

    public function assertVerifiedCount(int $times): void
    {
        Assert::assertCount(
            $times,
            $this->verifications,
            'Onverwacht aantal Turnstile-verificaties.',
        );
    }

    public function assertNothingVerified(): void
    {
        Assert::assertEmpty(
            $this->verifications,
            'Er is een Turnstile-token gecontroleerd terwijl dat niet mocht.',
        );
    }
}
