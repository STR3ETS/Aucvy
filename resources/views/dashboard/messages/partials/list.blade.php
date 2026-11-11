{{--
    De gesprekken, links op de berichtenpagina: één kaart met alle
    gesprekken als regels. Per regel de foto van de kavel, de titel, met wie
    je praat, wanneer het laatste bericht kwam en een label. Een ongelezen
    gesprek staat vet met een blauwe stip. Bij een langere lijst kun je
    zoeken; dat gebeurt in de browser.
    $items: de gesprekken, $current: het geopende gesprek (of null).
--}}
@php
    $tagFor = function ($conversation) use ($user): ?array {
        if ($conversation->isBlocked()) {
            return ['tone-grey', 'Gesloten'];
        }
        $order = $conversation->order;
        if ($order !== null) {
            return match ($order->status) {
                \App\Enums\OrderStatus::AwaitingPayment => ['tone-atlas', 'Te betalen'],
                \App\Enums\OrderStatus::Completed => ['tone-ok', 'Afgerond'],
                default => ['tone-atlas', $order->status->label()],
            };
        }
        if ($conversation->hasOperator()) {
            return ['tone-ivy', 'Medewerker leest mee'];
        }

        return $conversation->partLabel() ? ['tone-grey', $conversation->partLabel()] : null;
    };
    $totalConversations = method_exists($items, 'total') ? $items->total() : $items->count();
@endphp

<nav class="d-card d-convlist" aria-label="Gesprekken" x-data="{ q: '' }">
    <div class="d-titleline">
        <h2 class="d-h2">Gesprekken</h2>
        <span class="d-count tabular">{{ $totalConversations }}</span>
        <x-dashboard.help text="Elk gesprek hoort bij één kavel. Vragen, mail en de stand van de bestelling staan zo bij elkaar." />
    </div>

    @if ($items->count() > 6)
        <label class="d-convlist-search">
            <span class="sr-only">Zoek in je gesprekken</span>
            <input type="search" x-model="q" placeholder="Zoek op kavel of naam" autocomplete="off" class="d-input" style="margin:0">
        </label>
    @endif

    <div class="d-convlist-items">
        @forelse ($items as $conversation)
            @php
                $unread = $conversation->isUnreadFor($user);
                $tag = $tagFor($conversation);
                $isCurrent = $current !== null && $current->is($conversation);
                $counterpart = $conversation->counterpartLabel($user);
            @endphp
            <a href="{{ route('messages.show', $conversation) }}" @class(['d-conv', 'is-on' => $isCurrent, 'is-unread' => $unread && ! $isCurrent]) @if ($isCurrent) aria-current="page" @endif
               data-find="{{ Str::lower($conversation->lot->title.' '.$counterpart) }}" x-show="q === '' || $el.dataset.find.includes(q.toLowerCase())">
                <span class="d-conv-thumb" aria-hidden="true">
                    @if ($conversation->lot->media->isNotEmpty())
                        <img src="{{ $conversation->lot->media->first()->path }}" alt="" loading="lazy">
                    @endif
                </span>
                <span class="d-conv-body">
                    <strong>{{ $conversation->lot->title }}</strong>
                    <span>{{ $counterpart }}@if ($conversation->last_message_at) · {{ $conversation->last_message_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}@endif</span>
                    @if ($tag)
                        <span class="d-tag {{ $tag[0] }}">{{ $tag[1] }}</span>
                    @endif
                </span>
                @if ($unread && ! $isCurrent)
                    <span class="d-conv-unread" role="img" aria-label="Ongelezen"></span>
                @endif
            </a>
        @empty
            <p class="d-note">Nog geen gesprekken. Stel een vraag op een kavelpagina, of wacht op een bericht van een koper.</p>
        @endforelse
    </div>

    @if ($hasOlder ?? false)
        <div class="d-more"><a class="d-btn d-btn-secondary d-btn-sm" href="{{ route('messages.index', ['page' => 2]) }}">Oudere gesprekken</a></div>
    @endif
</nav>
