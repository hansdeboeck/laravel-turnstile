{{--
    De api.js van Cloudflare plus de gate van deze package.

    Geen onload-parameter: met render=explicit bestaat window.turnstile zodra het
    script uitgevoerd is, en het load-event van de tag is de garantie. Dat
    voorkomt dat twee widgets op dezelfde pagina om een globale vechten.

    :api="false" wanneer de pagina api.js zelf al binnenhaalt.
    :css="false"  wanneer de applicatie de stijlen zelf meebundelt.
--}}
@props([
    'api' => true,
    'css' => true,
])

@if (\HansDeBoeck\Turnstile\Facades\Turnstile::siteKey())
    @if ($css)
        <link rel="stylesheet" href="{!! \HansDeBoeck\Turnstile\TurnstileAssets::url('turnstile.css') !!}">
    @endif

    @if ($api)
        <script src="{{ config('turnstile.api_url') }}?render=explicit" async defer></script>
    @endif

    <script src="{!! \HansDeBoeck\Turnstile\TurnstileAssets::url('turnstile.js') !!}" defer></script>
@endif
