<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile;

use HansDeBoeck\Turnstile\Contracts\VerifiesTurnstile;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

class TurnstileVerifier implements VerifiesTurnstile
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config,
        protected Application $app,
    ) {}

    public function verify(?string $token, ?string $ip = null): TurnstileResult
    {
        if (! $this->configured()) {
            if ($this->app->environment($this->bypassEnvironments())) {
                return TurnstileResult::skipped();
            }

            // Een deployfout, geen bezoekersfout: dit hoort iemand te zien.
            $this->logger()->error('Turnstile is niet geconfigureerd; verificatie geweigerd.');

            return TurnstileResult::failed(TurnstileResult::FAILURE_NOT_CONFIGURED);
        }

        if ($token === null || trim($token) === '') {
            return TurnstileResult::failed(TurnstileResult::FAILURE_MISSING_TOKEN);
        }

        return $this->callCloudflare($token, $this->resolveIp($ip));
    }

    public function passes(?string $token, ?string $ip = null): bool
    {
        return $this->verify($token, $ip)->passed;
    }

    public function configured(): bool
    {
        return $this->present($this->config['secret'] ?? null) !== null;
    }

    public function siteKey(): ?string
    {
        return $this->present($this->config['key'] ?? null);
    }

    /**
     * De widget-standaarden voor de Blade-componenten en de JavaScript.
     *
     * @return array<string, mixed>
     */
    public function widgetDefaults(): array
    {
        $widget = $this->config['widget'] ?? [];

        return is_array($widget) ? $widget : [];
    }

    protected function callCloudflare(string $token, ?string $ip): TurnstileResult
    {
        try {
            $response = Http::asForm()
                ->connectTimeout((int) ($this->config['connect_timeout'] ?? 3))
                ->timeout((int) ($this->config['http_timeout'] ?? 5))
                ->post($this->verifyUrl(), array_filter([
                    'secret' => (string) $this->config['secret'],
                    'response' => $token,
                    'remoteip' => $ip,
                ], static fn ($value): bool => $value !== null && $value !== ''));
        } catch (Throwable $exception) {
            return $this->unreachable(['exception' => $exception->getMessage()]);
        }

        if (! $response->successful()) {
            return $this->unreachable(['status' => $response->status()]);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return $this->unreachable(['reason' => 'geen leesbare json']);
        }

        if (($payload['success'] ?? false) === true) {
            return TurnstileResult::success($payload);
        }

        $codes = array_values(array_filter(
            (array) ($payload['error-codes'] ?? []),
            static fn ($code): bool => is_string($code),
        ));

        // De codes horen in het logboek: 'invalid-input-secret' betekent dat de
        // sleutel fout staat, niet dat de bezoeker een bot is.
        $this->logger()->warning('Turnstile wees een token af.', ['error_codes' => $codes]);

        return TurnstileResult::failed(TurnstileResult::FAILURE_REJECTED, $codes);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function unreachable(array $context): TurnstileResult
    {
        $this->logger()->warning('Turnstile siteverify was niet bruikbaar.', $context);

        return ($this->config['fail_open'] ?? false)
            ? TurnstileResult::skipped()
            : TurnstileResult::failed(TurnstileResult::FAILURE_HTTP_ERROR);
    }

    /**
     * Null betekent "geen ip meesturen"; dat is voor Cloudflare toegestaan,
     * remoteip is optioneel en niet blokkerend.
     */
    protected function resolveIp(?string $explicit): ?string
    {
        if ($explicit !== null) {
            return $explicit;
        }

        $strategy = $this->config['remote_ip'] ?? 'request';

        if ($strategy === 'none') {
            return null;
        }

        $request = $this->app->bound('request') ? $this->app->make('request') : null;

        if (! $request instanceof Request) {
            return null;
        }

        if ($strategy === 'cloudflare') {
            return $request->header('CF-Connecting-IP') ?: $request->ip();
        }

        // Standaard: Request::ip(), zodat de trusted-proxy-instelling van de
        // applicatie geldt in plaats van een hard gecodeerde CDN-header.
        return $request->ip();
    }

    /**
     * @return list<string>
     */
    protected function bypassEnvironments(): array
    {
        $environments = $this->config['bypass_environments'] ?? null;

        // De fallback is geen overbodige voorzichtigheid: draait een deploy met
        // een gecachte config van voor deze package, dan bestaat de sleutel niet
        // en zou environment(null) midden in elk formulier een TypeError geven.
        if (! is_array($environments) || $environments === []) {
            return ['local', 'testing'];
        }

        return array_values(array_map(strval(...), $environments));
    }

    protected function verifyUrl(): string
    {
        $url = $this->config['verify_url'] ?? null;

        return is_string($url) && $url !== ''
            ? $url
            : 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    /**
     * Waarden die als "niet ingesteld" gelden worden hier afgevangen, zodat een
     * sentinel als 'NOTENV' nooit als echte sleutel de deur uit gaat.
     */
    protected function present(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $unset = (array) ($this->config['unset_values'] ?? []);

        return in_array($value, $unset, true) ? null : $value;
    }

    protected function logger(): LoggerInterface
    {
        $channel = $this->config['log_channel'] ?? null;

        return is_string($channel) && $channel !== ''
            ? Log::channel($channel)
            : Log::driver();
    }
}
