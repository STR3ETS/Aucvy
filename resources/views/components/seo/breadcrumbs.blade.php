@props([
    // [[naam, url], ...] van de home naar deze pagina.
    'items' => [],
])

{{-- Het kruimelpad voor zoekmachines (BreadcrumbList): Google toont het in plaats van de kale URL. --}}
<x-seo.schema :data="[
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($items)->values()->map(fn (array $item, int $index): array => [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'name' => $item[0],
        'item' => $item[1],
    ])->all(),
]" />
