{{--
    De klassieke, beheerde variant: Cloudflare rendert zelf en roept een globale
    callback aan. Gebruik dit alleen wanneer je de gate niet wil; voor een
    onzichtbare controle bij het versturen zijn <x-turnstile::stage> en
    <x-turnstile::container> de betere keuze.
--}}
@props([
    'callback' => null,
    'errorCallback' => null,
    'expiredCallback' => null,
    'theme' => null,
    'size' => null,
    'appearance' => null,
    'responseField' => null,
])

@php
    $defaults = \HansDeBoeck\Turnstile\Facades\Turnstile::widgetDefaults();
    $sitekey = \HansDeBoeck\Turnstile\Facades\Turnstile::siteKey();
@endphp

@if ($sitekey)
    <div class="cf-turnstile"
        data-sitekey="{{ $sitekey }}"
        data-theme="{{ $theme ?? ($defaults['theme'] ?? 'auto') }}"
        data-size="{{ $size ?? ($defaults['size'] ?? 'flexible') }}"
        @if ($appearance) data-appearance="{{ $appearance }}" @endif
        @if ($callback) data-callback="{{ $callback }}" @endif
        @if ($errorCallback) data-error-callback="{{ $errorCallback }}" @endif
        @if ($expiredCallback) data-expired-callback="{{ $expiredCallback }}" @endif
        @if ($responseField !== null) data-response-field-name="{{ $responseField }}" @endif
        {{ $attributes }}></div>
@endif
