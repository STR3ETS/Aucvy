@php
    // Op de eerste pagina staat het nieuwste bericht uitgelicht in de kop;
    // de rest volgt in het raster. Is er maar één bericht, dan staat het in beide.
    $featured = $page === 1 ? ($posts[0] ?? null) : null;
    $rest = $featured !== null && count($posts) > 1 ? array_slice($posts, 1) : $posts;
    $date = fn (array $post): ?string => $post['published_at']?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM YYYY');
@endphp

{{--
    De blog (ontwerp van november): de berichten komen uit het CMS van
    Eazyonline. Het nieuwste staat uitgelicht in de kop, de rest als kaarten.
--}}
<x-layouts.public title="Blog" eyebrow="Blog"
    description="Artikelen over zakelijk veilen, waardebepaling en het verkopen van bedrijfsmiddelen op Aucvy."
    :noindex="$page > 1">
    <x-slot:headline>Slimmer verkopen, <span class="g">beter bieden.</span></x-slot:headline>
    <x-slot:lead>
        <p style="margin:0">Over zakelijk veilen, waarde bepalen en slim verkopen van wat je niet meer gebruikt.</p>
    </x-slot:lead>
    @if ($featured)
        <x-slot:actions>
            <a class="d-blog-feature" href="{{ route('blog.show', $featured['slug']) }}">
                @if ($featured['image'])
                    <img src="{{ $featured['image'] }}" alt="{{ $featured['image_alt'] }}">
                @endif
                <span class="text"><span class="d-faq-label">Uitgelicht</span><strong>{{ $featured['title'] }}</strong></span>
            </a>
        </x-slot:actions>
    @endif

    <section class="d-sec" aria-labelledby="blog-title">
        <span class="d-pill">Artikelen</span>
        <h2 id="blog-title">Lees verder.</h2>

        @if ($posts === [])
            <p class="d-blog-empty">{{ $configured ? 'Er staan nog geen artikelen online.' : 'De blog komt binnenkort online.' }}</p>
        @else
            <div class="d-bloggrid">
                @foreach ($rest as $post)
                    <a class="d-blogcard" href="{{ route('blog.show', $post['slug']) }}">
                        <span class="cover">
                            @if ($post['image'])
                                <img src="{{ $post['image'] }}" alt="{{ $post['image_alt'] }}" loading="lazy">
                            @endif
                        </span>
                        <span class="text">
                            @if ($post['published_at'])
                                <time class="d-faq-label" datetime="{{ $post['published_at']->toDateString() }}">{{ $date($post) }}</time>
                            @endif
                            <strong>{{ $post['title'] }}</strong>
                            @if ($post['excerpt'] !== '')
                                <span class="excerpt">{{ $post['excerpt'] }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
                <a class="d-blogcard is-dark" href="{{ route('contact') }}">
                    <strong>Staat jouw vraag er niet bij?</strong>
                    <span>Stel hem aan ons team <x-v4.icon name="arrow-right" :size="13" stroke="2.4" /></span>
                </a>
            </div>

            @if ($last_page > 1)
                <nav class="d-blog-pages" aria-label="Paginering">
                    @if ($page > 1)
                        <a href="{{ route('blog.index', $page > 2 ? ['pagina' => $page - 1] : []) }}" class="d-btn d-btn-secondary d-btn-sq" rel="prev">Nieuwer</a>
                    @endif
                    <span>Pagina {{ $page }} van {{ $last_page }}</span>
                    @if ($page < $last_page)
                        <a href="{{ route('blog.index', ['pagina' => $page + 1]) }}" class="d-btn d-btn-secondary d-btn-sq" rel="next">Ouder</a>
                    @endif
                </nav>
            @endif
        @endif
    </section>

    <x-marketing.cta text="Bedrijven plaatsen gratis. Ivy herkent, Nova schrijft en Atlas waardeert."
        primary="Kavel plaatsen" :primary-url="route('sell')"
        secondary="Veilingen bekijken" :secondary-url="route('lots.index')">
        Meteen zien wat <span class="g">jouw kavel waard is?</span>
    </x-marketing.cta>
</x-layouts.public>
