<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sleutels
    |--------------------------------------------------------------------------
    |
    | De publieke sitekey en de geheime sleutel uit het Cloudflare-dashboard.
    | Blijft de sitekey leeg, dan renderen de Blade-componenten niets in plaats
    | van een sleutel die Cloudflare toch afwijst.
    |
    */

    'key' => env('TURNSTILE_KEY'),

    'secret' => env('TURNSTILE_SECRET'),

    /*
    | Waarden die als "niet ingesteld" gelden naast null en de lege string. Handig
    | wanneer een omgeving ooit een sentinel als env-default gebruikte.
    */

    'unset_values' => ['NOTENV'],

    /*
    |--------------------------------------------------------------------------
    | Omgevingen zonder verplichte configuratie
    |--------------------------------------------------------------------------
    |
    | Ontbreekt de geheime sleutel, dan slaagt de controle enkel in deze
    | omgevingen. Overal anders faalt ze, zodat een ontbrekende sleutel op
    | staging of productie nooit ongemerkt voorbijgaat. Staat de sleutel wel
    | ingesteld, dan wordt er ook hier echt geverifieerd.
    |
    */

    'bypass_environments' => ['local', 'testing'],

    /*
    |--------------------------------------------------------------------------
    | Netwerk
    |--------------------------------------------------------------------------
    |
    | Samen de harde bovengrens per verificatie. Er wordt bewust niet opnieuw
    | geprobeerd: een Turnstile-token is eenmalig, dus een tweede poging levert
    | bij een half geslaagde eerste poging gegarandeerd 'timeout-or-duplicate'.
    |
    */

    'connect_timeout' => env('TURNSTILE_CONNECT_TIMEOUT', 3),

    'http_timeout' => env('TURNSTILE_HTTP_TIMEOUT', 5),

    /*
    | Wat te doen wanneer Cloudflare onbereikbaar is. false weigert (de veilige
    | keuze), true laat door. Zet dit enkel om wanneer een formulier belangrijker
    | is dan de botfilter, bijvoorbeeld tijdens een storing.
    */

    'fail_open' => env('TURNSTILE_FAIL_OPEN', false),

    /*
    | Welk ip als 'remoteip' meegaat: 'request', 'cloudflare' of 'none'.
    | 'request' gebruikt Request::ip() en volgt dus de trusted-proxy-instelling
    | van de applicatie. 'cloudflare' laat de CF-Connecting-IP-header voorgaan.
    */

    'remote_ip' => env('TURNSTILE_REMOTE_IP', 'request'),

    'log_channel' => env('TURNSTILE_LOG_CHANNEL'),

    /*
    |--------------------------------------------------------------------------
    | Eindpunten
    |--------------------------------------------------------------------------
    */

    'verify_url' => env('TURNSTILE_VERIFY_URL', 'https://challenges.cloudflare.com/turnstile/v0/siteverify'),

    'api_url' => env('TURNSTILE_API_URL', 'https://challenges.cloudflare.com/turnstile/v0/api.js'),

    /*
    |--------------------------------------------------------------------------
    | Widget
    |--------------------------------------------------------------------------
    |
    | Standaardwaarden voor de Blade-componenten en de JavaScript. Per component
    | te overschrijven.
    |
    */

    'widget' => [
        'theme' => 'auto',
        'size' => 'flexible',
        'language' => 'auto',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gepubliceerde assets
    |--------------------------------------------------------------------------
    |
    | 'path' is waar `vendor:publish --tag=turnstile-assets` de bestanden zet,
    | relatief aan public/. 'resolver' is een optionele callable die een
    | public-pad naar een URL vertaalt; laat null voor de ingebouwde
    | filemtime-versionering met .min-omschakeling in productie.
    |
    */

    'assets' => [
        'path' => 'vendor/turnstile',
        'resolver' => null,
    ],

];
