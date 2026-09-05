{{--
    Het vlak waarin een uitdaging naar voren komt.

    Het staat bewust in de stroom met hoogte 0 en niet op display:none: een
    widget dat rendert in een verborgen container blijft leeg. De JavaScript
    wisselt data-turnstile-stage naar "challenge" en zet inert.

    Geef `fixed` mee wanneer er geen positioned ouder is (buiten een dialoog),
    dan meet het vlak zich aan het venster in plaats van aan de pagina.

    Zonder sitekey rendert dit niets: beter geen vlak dan een leeg vlak.
--}}
@props([
    'fixed' => false,
    'for' => null,
    'labelledby' => null,
    'dialog' => true,
])

@if (\HansDeBoeck\Turnstile\Facades\Turnstile::siteKey())
    {{-- Zet :dialog="false" wanneer het vlak zelf al binnen een <dialog> staat:
         een geneste modal-rol maakt het voor een schermlezer alleen verwarrender. --}}
    <div data-turnstile-stage="idle"
        @if ($fixed) data-turnstile-fixed @endif
        @if ($for) data-turnstile-for="{{ $for }}" @endif
        @if ($dialog) role="dialog" aria-modal="true" @endif
        @if ($labelledby) aria-labelledby="{{ $labelledby }}" @endif
        {{ $attributes }}>
        {{ $slot }}
    </div>
@endif
