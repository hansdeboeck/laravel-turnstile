{{--
    Het verborgen veld waar het token in belandt.

    De JavaScript zoekt op [data-turnstile-field] binnen het formulier, nooit op
    id, zodat meerdere formulieren op een pagina elkaar niet in de weg zitten.
--}}
@props([
    'name' => 'turnstile_token',
    'id' => null,
])

<input type="hidden" name="{{ $name }}" @if ($id) id="{{ $id }}" @endif data-turnstile-field
    {{ $attributes }}>
