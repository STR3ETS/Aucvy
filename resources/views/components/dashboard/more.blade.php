@props([
    // Hoeveel rijen de lijst telt en hoeveel er open staan.
    'total',
    'limit' => 10,
])

{{--
    "Meer tonen" onder een lange lijst: de eerste rijen staan open, de rest
    komt erbij met één klik. De lijst zelf staat in een Alpine-scope met
    `all` (x-data="{ all: false }"); rijen vanaf de grens krijgen
    x-show="all" en x-cloak.
--}}
@if ($total > $limit)
    <div {{ $attributes->merge(['class' => 'd-more']) }} x-show="! all">
        <button type="button" class="d-btn d-btn-secondary d-btn-sm" @click="all = true">Meer tonen <span class="tabular">({{ $total - $limit }})</span></button>
    </div>
@endif
