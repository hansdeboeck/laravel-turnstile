<?php

declare(strict_types=1);

namespace HansDeBoeck\Turnstile\Rules;

use Closure;
use HansDeBoeck\Turnstile\Contracts\VerifiesTurnstile;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * De objectvorm van de regel, voor wie een eigen boodschap of een expliciet ip
 * wil. De stringvorm 'turnstile' doet hetzelfde en wordt door de
 * ServiceProvider geregistreerd.
 */
class Turnstile implements ValidationRule
{
    public function __construct(
        protected ?string $ip = null,
        protected ?string $message = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail($this->message ?? static::defaultMessage());

            return;
        }

        $verifier = Container::getInstance()->make(VerifiesTurnstile::class);

        if (! $verifier->passes($value, $this->ip)) {
            $fail($this->message ?? static::defaultMessage());
        }
    }

    public static function defaultMessage(): string
    {
        $translator = Container::getInstance()->make('translator');
        $message = $translator->get('turnstile::validation.turnstile');

        // Ontbreekt de vertaling, dan geeft de translator de sleutel terug.
        return is_string($message) && $message !== 'turnstile::validation.turnstile'
            ? $message
            : 'The verification did not succeed. Please try again.';
    }
}
