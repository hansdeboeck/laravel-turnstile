<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * De uitkomst van een verificatie.
 *
 * Let op de scheiding tussen wat je mag loggen en wat je aan een bezoeker mag
 * tonen: `errorCodes` bevat de rauwe Cloudflare-codes, waaronder
 * `invalid-input-secret`. Die horen in het logboek, niet in een respons, en
 * staan daarom bewust niet in toArray() of jsonSerialize().
 *
 * @implements Arrayable<string, mixed>
 */
final class TurnstileResult implements Arrayable, JsonSerializable
{
    public const FAILURE_MISSING_TOKEN = 'missing-token';

    public const FAILURE_NOT_CONFIGURED = 'not-configured';

    public const FAILURE_HTTP_ERROR = 'http-error';

    public const FAILURE_REJECTED = 'rejected';

    /**
     * @param  list<string>  $errorCodes
     */
    private function __construct(
        public readonly bool $passed,
        public readonly ?string $failure = null,
        public readonly array $errorCodes = [],
        public readonly bool $skipped = false,
        public readonly ?string $hostname = null,
        public readonly ?string $action = null,
        public readonly ?string $cdata = null,
        public readonly ?string $challengeTs = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  De body van siteverify.
     */
    public static function success(array $payload = []): self
    {
        return new self(
            passed: true,
            hostname: self::string($payload, 'hostname'),
            action: self::string($payload, 'action'),
            cdata: self::string($payload, 'cdata'),
            challengeTs: self::string($payload, 'challenge_ts'),
        );
    }

    /**
     * Geslaagd zonder dat er iets gecontroleerd is: de omgeving slaat de
     * controle over omdat er geen sleutel ingesteld staat.
     */
    public static function skipped(): self
    {
        return new self(passed: true, skipped: true);
    }

    /**
     * @param  list<string>  $errorCodes
     */
    public static function failed(string $failure, array $errorCodes = []): self
    {
        return new self(passed: false, failure: $failure, errorCodes: $errorCodes);
    }

    /**
     * Waar of de bezoeker de uitdaging kreeg maar niet doorstond, in
     * tegenstelling tot een storing aan onze kant.
     */
    public function rejected(): bool
    {
        return $this->failure === self::FAILURE_REJECTED;
    }

    /**
     * Veilig om naar een client te sturen: nooit de foutcodes van Cloudflare.
     *
     * @return array{passed: bool, skipped: bool, failure: string|null}
     */
    public function toArray(): array
    {
        return [
            'passed' => $this->passed,
            'skipped' => $this->skipped,
            'failure' => $this->failure,
        ];
    }

    /**
     * @return array{passed: bool, skipped: bool, failure: string|null}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Alles wat in het logboek mag, foutcodes inbegrepen.
     *
     * @return array<string, mixed>
     */
    public function forLogging(): array
    {
        return [
            'passed' => $this->passed,
            'skipped' => $this->skipped,
            'failure' => $this->failure,
            'error_codes' => $this->errorCodes,
            'hostname' => $this->hostname,
            'action' => $this->action,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function string(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
