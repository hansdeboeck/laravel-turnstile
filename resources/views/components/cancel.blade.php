{{--
    De knop waarmee de bezoeker een uitdaging afbreekt. De JavaScript bindt op
    [data-turnstile-cancel] binnen het vlak.
--}}
<button type="button" data-turnstile-cancel {{ $attributes }}>{{ $slot }}</button>
