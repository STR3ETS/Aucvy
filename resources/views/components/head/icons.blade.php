{{--
    Het icoon van Aucvy in elke layout: een SVG voor moderne browsers, de
    ICO als terugval, het icoon voor het beginscherm van een telefoon en het
    webmanifest. De kleur van de browserbalk volgt de navy kop.
--}}
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#0c1326">
<meta name="application-name" content="{{ config('app.name') }}">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">
