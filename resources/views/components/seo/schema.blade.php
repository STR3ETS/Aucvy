@props(['data'])

{{-- Gestructureerde gegevens voor zoekmachines (schema.org), als JSON-LD. --}}
<script type="application/ld+json">{!! json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
