{{--
    De render-target van het widget zelf. Cloudflare hangt hier zijn iframe in.
--}}
@props([
    'theme' => null,
    'size' => null,
])

@php
    $defaults = \HansDeBoeck\Turnstile\Facades\Turnstile::widgetDefaults();
    $sitekey = \HansDeBoeck\Turnstile\Facades\Turnstile::siteKey();
@endphp

@if ($sitekey)
    <div data-turnstile-container
        data-sitekey="{{ $sitekey }}"
        data-theme="{{ $theme ?? ($defaults['theme'] ?? 'auto') }}"
        data-size="{{ $size ?? ($defaults['size'] ?? 'flexible') }}"
        {{ $attributes }}></div>
@endif
