@props(['count' => 14])

{{--
    De zwevende stipjes op de donkere vlakken. Zelfde posities en timing als
    het ontwerp: de i-de stip staat op een vast raster, zodat elke lading
    hetzelfde beeld geeft.
--}}
<div class="v4-particles" aria-hidden="true">
    @for ($i = 0; $i < $count; $i++)
        @php
            $left = ((7 - 17 * $i) % 100 + 100) % 100;
            $top = 13 + $i + ($i % 2 === 1 ? 46 : 0);
            $size = 2 + $i % 3;
            $duration = 7 + 2.2 * ($i % 5);
            $delay = 1.1 * ($i % 7);
        @endphp
        <span class="v4-particle" style="left:{{ $left }}%;top:{{ $top }}%;width:{{ $size }}px;height:{{ $size }}px;animation-duration:{{ $duration }}s;animation-delay:{{ $delay }}s"></span>
    @endfor
</div>
