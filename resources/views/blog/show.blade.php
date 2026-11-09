@php
    // Staat er een dubbele punt in de titel, dan krijgt het deel erna het verloop.
    [$titleStart, $titleEnd] = str_contains($post['title'], ': ')
        ? explode(': ', $post['title'], 2)
        : [$post['title'], null];
    $published = $post['published_at']?->timezone(config('aucvy.display_timezone'));
@endphp

{{--
    Een blogbericht (ontwerp van november): de kop met de inleiding, links de
    inhoudsopgave uit de tussenkoppen en rechts de tekst uit het CMS.
--}}
<x-layouts.public :title="$post['meta_title']" eyebrow="Blog"
    :description="$post['meta_description']"
    :image="$post['image']"
    og-type="article">
    <x-slot:headline>{{ $titleStart }}@if ($titleEnd !== null): <span class="g">{{ $titleEnd }}</span>@endif</x-slot:headline>
    <x-slot:lead>
        @if ($post['excerpt'] !== '')
            <p style="margin:0">{{ $post['excerpt'] }}</p>
        @endif
        <div class="page-actions" style="margin-top:18px">
            <a class="btn btn-primary" href="#artikel">Lees het artikel <x-v4.icon name="arrow-right" :size="15" stroke="2.4" /></a>
            <a class="btn btn-outline-white" href="{{ route('blog.index') }}">Meer artikelen</a>
        </div>
    </x-slot:lead>
    @if ($post['image'])
        <x-slot:actions>
            <img class="d-article-cover" src="{{ $post['image'] }}" alt="{{ $post['image_alt'] }}">
        </x-slot:actions>
    @endif

    <div class="d-article" id="artikel">
        <aside aria-label="In dit artikel">
            @if ($outline['items'] !== [])
                <span class="d-article-label">In dit artikel</span>
                <ol>
                    @foreach ($outline['items'] as $index => $item)
                        <li><a href="#{{ $item['id'] }}"><b>{{ $index + 1 }}</b>{{ $item['title'] }}</a></li>
                    @endforeach
                </ol>
            @endif
            <p class="d-article-meta">
                @if ($post['author'])
                    {{ $post['author'] }}
                @endif
                @if ($published)
                    {{ $post['author'] ? '·' : '' }}
                    <time datetime="{{ $published->toDateString() }}">{{ $published->isoFormat('D MMMM YYYY') }}</time>
                @endif
            </p>
        </aside>

        {{-- Door SafeHtml gegaan: alleen opmaak, geen scripts of event-attributen. --}}
        <article class="d-article-body">
            {!! $outline['html'] !!}

            <div class="d-article-tip">
                <strong>Zelf iets te verkopen?</strong>
                Bij het plaatsen zie je de waarde-indicatie en het advies van Finn. Plaatsen is gratis; je betaalt alleen bij een geslaagde verkoop.
            </div>
            <p><a href="{{ route('blog.index') }}">Alle artikelen</a></p>
        </article>
    </div>

    <x-marketing.cta text="Bedrijven plaatsen gratis. Ivy herkent, Nova schrijft en Atlas waardeert."
        primary="Kavel plaatsen" :primary-url="route('sell')"
        secondary="Meer artikelen" :secondary-url="route('blog.index')">
        Meteen zien wat <span class="g">jouw kavel waard is?</span>
    </x-marketing.cta>

    @php
        $structured = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post['title'],
            'description' => $post['meta_description'],
            'image' => $post['image'],
            'datePublished' => $post['published_at']?->toAtomString(),
            'dateModified' => ($post['updated_at'] ?? $post['published_at'])?->toAtomString(),
            'author' => $post['author'] ? ['@type' => 'Person', 'name' => $post['author']] : ['@type' => 'Organization', 'name' => config('app.name')],
            'mainEntityOfPage' => route('blog.show', $post['slug']),
        ];
    @endphp
    @push('head')
        <script type="application/ld+json">{!! json_encode(array_filter($structured, fn ($value) => $value !== null), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush
</x-layouts.public>
