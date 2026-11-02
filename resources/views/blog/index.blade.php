<x-layouts.public title="Blog" eyebrow="Aucvy" heading="Blog"
    intro="Over zakelijk veilen, waarde bepalen en slim verkopen van wat je niet meer gebruikt."
    description="Artikelen over zakelijk veilen, waardebepaling en het verkopen van bedrijfsmiddelen op Aucvy."
    :noindex="$page > 1">
    <div class="mx-auto max-w-6xl">
        @if ($posts === [])
            <p class="rounded-aj-s border border-dashed border-aj-border-strong bg-aj-surface p-8 text-center text-sm text-aj-muted">
                {{ $configured ? 'Er staan nog geen artikelen online.' : 'De blog komt binnenkort online.' }}
            </p>
        @else
            <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <li>
                        <a href="{{ route('blog.show', $post['slug']) }}" class="group flex h-full flex-col overflow-hidden rounded-aj-s border border-aj-border bg-aj-surface hover:border-aj-border-strong">
                            <span class="block aspect-[16/9] w-full bg-aj-navy">
                                @if ($post['image'])
                                    <img src="{{ $post['image'] }}" alt="{{ $post['image_alt'] }}" loading="lazy" class="h-full w-full object-cover">
                                @endif
                            </span>
                            <span class="flex flex-1 flex-col p-5">
                                @if ($post['published_at'])
                                    <time class="text-xs font-bold uppercase tracking-wide text-aj-muted" datetime="{{ $post['published_at']->toDateString() }}">
                                        {{ $post['published_at']->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM YYYY') }}
                                    </time>
                                @endif
                                <span class="mt-1 text-lg font-extrabold group-hover:underline">{{ $post['title'] }}</span>
                                @if ($post['excerpt'] !== '')
                                    <span class="mt-2 line-clamp-3 text-sm text-aj-soft">{{ $post['excerpt'] }}</span>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if ($last_page > 1)
                <nav class="mt-8 flex items-center justify-center gap-3 text-sm" aria-label="Paginering">
                    @if ($page > 1)
                        <a href="{{ route('blog.index', $page > 2 ? ['pagina' => $page - 1] : []) }}" class="btn btn-ghost" rel="prev">Nieuwer</a>
                    @endif
                    <span class="tabular text-aj-muted">Pagina {{ $page }} van {{ $last_page }}</span>
                    @if ($page < $last_page)
                        <a href="{{ route('blog.index', ['pagina' => $page + 1]) }}" class="btn btn-ghost" rel="next">Ouder</a>
                    @endif
                </nav>
            @endif
        @endif
    </div>
</x-layouts.public>
