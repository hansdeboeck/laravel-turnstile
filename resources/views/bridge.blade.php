<!doctype html>
{{--
    Kale botcheck-pagina voor een WebView.

    Een native app kan zelf geen Turnstile-token maken. Die laadt deze pagina in
    een onzichtbare WebView, wacht op een bericht en stuurt het token daarna mee
    met haar eigen API-verzoek. Blijft de controle stil, dan blijft de WebView
    onzichtbaar; vraagt Cloudflare iets, dan meldt de pagina dat zodat de app
    haar zichtbaar kan maken.

    Berichten (JSON, via window.ReactNativeWebView.postMessage):
      {"status":"ok","token":"..."}   het token is klaar
      {"status":"error"}              de controle is mislukt of verlopen
      {"status":"interactive"}        Cloudflare wil interactie, toon de WebView
      {"status":"resolved"}           de interactie is voorbij, verberg hem weer

    Route bij de applicatie: Route::view('/turnstile', 'turnstile::bridge').
--}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Verificatie' }}</title>
    <link rel="stylesheet" href="{!! \HansDeBoeck\Turnstile\TurnstileAssets::url('turnstile.css') !!}">
    <script src="{{ config('turnstile.api_url') }}?render=explicit" async defer></script>
    <script src="{!! \HansDeBoeck\Turnstile\TurnstileAssets::url('turnstile-bridge.js') !!}" defer></script>
</head>
<body data-turnstile-bridge>
    <x-turnstile::container data-theme="{{ $theme ?? 'light' }}" />
</body>
</html>
