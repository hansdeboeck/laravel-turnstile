<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile\Contracts;

use HansDeBoeck\Turnstile\TurnstileResult;

interface VerifiesTurnstile
{
    /**
     * Controleer een token bij Cloudflare.
     *
     * Laat $ip op null om hem uit de lopende request af te leiden; dat is niet
     * hetzelfde als geen ip meesturen. Wie bewust niets wil meesturen, zet
     * `turnstile.remote_ip` op 'none'.
     */
    public function verify(?string $token, ?string $ip = null): TurnstileResult;

    public function passes(?string $token, ?string $ip = null): bool;

    /** Is er een bruikbare geheime sleutel? Bepaalt of verify() uberhaupt HTTP doet. */
    public function configured(): bool;

    /** De publieke sitekey voor de frontend, of null wanneer die ontbreekt. */
    public function siteKey(): ?string;
}
