@props([
    // De titel in de zoekresultaten; null geeft de standaardtitel van de site.
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'imageWidth' => null,
    'imageHeight' => null,
    'ogType' => 'website',
    'noindex' => false,
])

@php
    $siteTitle = 'Aucvy: zakelijke veilingen voor bedrijfsgoederen';
    $fullTitle = $title ?: $siteTitle;
    // Google toont rond de 155 tekens; een langere zin wordt netjes ingekort.
    $metaDescription = $description !== null && trim($description) !== ''
        ? Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($description))), 152)
        : null;
    $canonicalUrl = $canonical ?? url()->current();
    $shareImage = $image ?? asset('images/v4/footer-gavel.jpg');
@endphp

{{--
    De zoek- en deelkop van een openbare pagina: titel, beschrijving,
    canonical, Open Graph en de kaart voor X. Eén plek voor de layouts public
    en v4, zodat elke variant van de home-test dezelfde canonical heeft.
--}}
<title>{{ $fullTitle }}</title>
@if ($metaDescription)
    <meta name="description" content="{{ $metaDescription }}">
@endif
<link rel="canonical" href="{{ $canonicalUrl }}">
@if ($noindex)
    <meta name="robots" content="noindex,follow">
@else
    <meta name="robots" content="index,follow,max-image-preview:large">
@endif
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="nl_NL">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:title" content="{{ $fullTitle }}">
@if ($metaDescription)
    <meta property="og:description" content="{{ $metaDescription }}">
@endif
<meta property="og:image" content="{{ $shareImage }}">
@if ($imageWidth && $imageHeight)
    <meta property="og:image:width" content="{{ $imageWidth }}">
    <meta property="og:image:height" content="{{ $imageHeight }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
@if ($metaDescription)
    <meta name="twitter:description" content="{{ $metaDescription }}">
@endif
<meta name="twitter:image" content="{{ $shareImage }}">
