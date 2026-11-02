<x-layouts.public :title="$post['meta_title']" eyebrow="Blog" :heading="$post['title']"
    :intro="$post['excerpt'] !== '' ? $post['excerpt'] : null"
    :description="$post['meta_description']"
    :image="$post['image']"
    og-type="article">
    <article class="mx-auto max-w-3xl">
        <p class="text-sm text-aj-muted">
            @if ($post['author'])
                {{ $post['author'] }}
            @endif
            @if ($post['published_at'])
                {{ $post['author'] ? '·' : '' }}
                <time datetime="{{ $post['published_at']->toDateString() }}">{{ $post['published_at']->timezone(config('aucvy.display_timezone'))->isoFormat('D MMMM YYYY') }}</time>
            @endif
        </p>

        @if ($post['image'])
            <img src="{{ $post['image'] }}" alt="{{ $post['image_alt'] }}" class="mt-5 w-full rounded-aj-s border border-aj-border object-cover">
        @endif

        {{-- Door SafeHtml gegaan: alleen opmaak, geen scripts of event-attributen. --}}
        <div class="blog-content mt-6 space-y-4 text-base leading-relaxed [&_a]:font-semibold [&_a]:underline [&_blockquote]:border-l-4 [&_blockquote]:border-aj-blue [&_blockquote]:pl-4 [&_h2]:mt-8 [&_h2]:text-2xl [&_h2]:font-extrabold [&_h3]:mt-6 [&_h3]:text-xl [&_h3]:font-extrabold [&_img]:rounded-aj-sm [&_ol]:list-decimal [&_ol]:pl-6 [&_table]:w-full [&_td]:border [&_td]:border-aj-border [&_td]:p-2 [&_th]:border [&_th]:border-aj-border [&_th]:p-2 [&_ul]:list-disc [&_ul]:pl-6">
            {!! $post['content'] !!}
        </div>

        <p class="mt-10"><a href="{{ route('blog.index') }}" class="font-semibold underline">Alle artikelen</a></p>
    </article>

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
