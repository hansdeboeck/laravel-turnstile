# Laravel Turnstile

Cloudflare Turnstile voor Laravel: serverside verificatie, een validatieregel,
Blade-componenten en de bijhorende JavaScript.

Het widget draait standaard onzichtbaar (`appearance: interaction-only`,
`execution: execute`). Er gebeurt pas iets wanneer een formulier verstuurd wordt,
en de bezoeker ziet alleen iets wanneer Cloudflare echt twijfelt. Komt die
uitdaging er, dan schuift ze naar voren als modaal blok in plaats van ergens in
de paginastroom op te duiken waar niemand ze ziet.

## Installatie

```bash
composer require hansdeboeck/laravel-turnstile
php artisan vendor:publish --tag=turnstile-assets
```

In `.env`:

```dotenv
TURNSTILE_KEY=0x...
TURNSTILE_SECRET=0x...
```

Voeg `https://challenges.cloudflare.com` toe aan `script-src` en `frame-src` van
je Content-Security-Policy.

`vendor:publish --tag=turnstile-assets --force` hoort in je deploy-stap: zonder
dat belandt een update van de JavaScript nooit in `public/`.

## Serverzijde

```php
use HansDeBoeck\Turnstile\Facades\Turnstile;

// Als validatieregel, de kortste weg:
$request->validate([
    'turnstile_token' => 'required|string|turnstile',
]);

// Of expliciet, wanneer de vololgorde van je eigen controles ertoe doet:
if (! Turnstile::passes($request->input('turnstile_token'))) {
    return back()->withErrors(['turnstile_token' => 'Verificatie mislukt.']);
}

// Met het volledige resultaat:
$result = Turnstile::verify($token);
$result->passed;      // bool
$result->skipped;     // geslaagd omdat de omgeving de controle overslaat
$result->failure;     // missing-token | not-configured | http-error | rejected
$result->forLogging();
```

`verify()` en `passes()` leiden het ip zelf uit de lopende request af. Geef een
tweede argument mee om dat te overschrijven, of zet `turnstile.remote_ip` op
`'none'` om helemaal geen ip mee te sturen.

De foutcodes van Cloudflare (`invalid-input-secret` en broertjes) zitten in
`errorCodes` en `forLogging()`, maar bewust **niet** in `toArray()` of
`jsonSerialize()`. Stuur ze nooit naar een browser: ze verraden hoe je
configuratie erbij ligt.

### Ontbrekende sleutels

Zonder geheime sleutel slaagt de controle enkel in `local` en `testing`
(instelbaar via `turnstile.bypass_environments`). Overal anders faalt ze met
`not-configured` plus een `error`-regel in het logboek, zodat een vergeten
sleutel op staging of productie niet ongemerkt voorbijgaat. Staat de sleutel
wel ingesteld, dan wordt er ook lokaal echt geverifieerd.

## Frontend

Zonder eigen JavaScript, via de auto-bedrading:

```blade
<form method="post" action="{{ route('contact.store') }}" data-turnstile-form="contact">
    @csrf
    <x-turnstile::field />
    <button type="submit">Versturen</button>
</form>

<x-turnstile::stage for="contact" fixed labelledby="contact-turnstile-titel"
    class="jouw-overlay-klassen">
    <div class="jouw-paneel-klassen">
        <h2 id="contact-turnstile-titel">Even controleren</h2>
        <p>Cloudflare wil kort nagaan of je geen robot bent.</p>
        <x-turnstile::container class="jouw-container-klassen" />
        <x-turnstile::cancel class="jouw-knop-klassen">Annuleren</x-turnstile::cancel>
    </div>
</x-turnstile::stage>

@push('scripts')
    <x-turnstile::scripts />
@endpush
```

De componenten leveren alleen gedrag: data-attributen, aria-bedrading en de
sitekey. Klassen komen van jou, in je eigen views, waar je CSS-bouwstap al kijkt.

Zonder sitekey renderen `stage`, `container`, `scripts` en `widget` niets.

### Programmatisch

```js
const gate = window.turnstileGate.create({
    stage: document.querySelector('[data-turnstile-stage]'),
    container: '#mijn-container',
});

gate.token()
    .then((token) => { /* meesturen met je fetch */ })
    .catch((error) => {
        if (gate.isCancelled(error)) return;    // bezoeker brak zelf af
        if (gate.isExpired(error)) return;      // token verliep, opnieuw proberen
        // echte storing
    });

gate.reset();
gate.destroy();   // nodig zodra een dialoog uit de DOM verdwijnt
```

Foutcodes: `turnstile-script`, `turnstile-error`, `turnstile-cancelled`,
`turnstile-expired`, `turnstile-superseded`, `turnstile-not-ready`.

### Zichtbaar widget

Wil je het klassieke aanvinkvakje in plaats van een controle bij het versturen,
geef de container dan `data-turnstile-visible`, of gebruik `create({visible: true})`.
Voor volledig eigen bedrading is er `<x-turnstile::widget data-callback="..." />`.

### Styling

`turnstile.css` regelt alleen de mechaniek van het vlak. Kleuren en maten lopen
via custom properties:

```css
:root {
    --turnstile-stage-bg: var(--color-surface);
    --turnstile-overlay-bg: rgb(0 0 0 / 0.6);
    --turnstile-stage-z: 50;
    --turnstile-stage-padding: 1.5rem;
}
```

Heeft je applicatie een eigen asset-helper (cachebusting, `.min`-omschakeling),
zet die dan in `turnstile.assets.resolver`:

```php
'resolver' => [\App\Providers\AppServiceProvider::class, 'assetv'],
```

De resolver krijgt `$path` en levert een URL die klaar is om in een attribuut te
echoen (dus reeds ge-escaped).

## Native apps

Een native app kan zelf geen token maken. Publiceer daarvoor de kale brugpagina
en laad ze in een onzichtbare WebView:

```php
Route::view('/turnstile', 'turnstile::bridge')->middleware('throttle:60,1');
```

De pagina stuurt JSON via `window.ReactNativeWebView.postMessage`:
`{"status":"ok","token":"..."}`, `{"status":"error"}`, `{"status":"interactive"}`
(toon de WebView) en `{"status":"resolved"}` (verberg hem weer).

## Testen

```php
use HansDeBoeck\Turnstile\Facades\Turnstile;

$fake = Turnstile::fake();              // alles slaagt
Turnstile::fake(fn ($token) => $token === 'goed');
Turnstile::fakeFailure();               // alles faalt
Turnstile::fakeUnconfigured();          // doet alsof de sleutels ontbreken

$fake->assertVerified('goed');
$fake->assertVerifiedCount(1);
$fake->assertNothingVerified();
```

De fake vervangt de facade, het contract en de concrete klasse, dus ook de
validatieregel en code die `TurnstileVerifier` type-hint zien hem.

Wil je liever op HTTP-niveau blijven, dan werkt `Http::fake()` gewoon:

```php
Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);
```

### Testsleutels van Cloudflare

| sitekey | secret | gedrag |
| --- | --- | --- |
| `1x00000000000000000000AA` | `1x0000000000000000000000000000000AA` | slaagt altijd, onzichtbaar |
| `3x00000000000000000000FF` | | daagt altijd uit |
| | `2x0000000000000000000000000000000AA` | siteverify faalt altijd |

## Configuratie

```bash
php artisan vendor:publish --tag=turnstile-config
```

De sleutels staan met commentaar in `config/turnstile.php`. Het vaakst gebruikt:
`bypass_environments`, `connect_timeout` / `http_timeout` (samen de harde
bovengrens per verificatie), `fail_open` (doorlaten bij een Cloudflare-storing)
en `remote_ip`.

Er wordt bewust **niet** opnieuw geprobeerd bij een netwerkfout: een
Turnstile-token is eenmalig, dus een tweede poging levert bij een half geslaagde
eerste poging gegarandeerd `timeout-or-duplicate`, en dat is niet van bot-gedrag
te onderscheiden.

## Licentie

MIT.
