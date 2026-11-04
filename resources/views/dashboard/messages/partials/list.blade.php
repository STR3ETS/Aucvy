{{--
    De gesprekken per kavel, links in de berichtenpagina (ontwerp van
    november): foto, titel, tegenpartij, laatste stand en een label.
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
@endphp

<nav class="d-stack-tight" aria-label="Gesprekken per kavel">
    <span class="d-aside-label" style="display:block">Gesprekken per kavel</span>
    @forelse ($items as $conversation)
        @php $unread = $conversation->isUnreadFor($user); $tag = $tagFor($conversation); $isCurrent = $current !== null && $current->is($conversation); @endphp
        <a href="{{ route('messages.show', $conversation) }}" class="d-conv{{ $isCurrent ? ' is-on' : '' }}" @if ($isCurrent) aria-current="page" @endif>
            <span class="d-conv-thumb" aria-hidden="true">
                @if ($conversation->lot->media->isNotEmpty())
                    <img src="{{ $conversation->lot->media->first()->path }}" alt="">
                @endif
            </span>
            <span class="d-conv-body">
                <strong>{{ $conversation->lot->title }}</strong>
                <span>{{ $conversation->counterpartLabel($user) }}@if ($conversation->last_message_at) · {{ $conversation->last_message_at->timezone(config('aucvy.display_timezone'))->diffForHumans() }}@endif</span>
                @if ($tag)
                    <span class="d-tag {{ $tag[0] }}" style="align-self:flex-start;margin-top:4px">{{ $tag[1] }}</span>
                @endif
            </span>
            @if ($unread)
                <span class="d-conv-unread" aria-label="Ongelezen">1</span>
            @endif
        </a>
    @empty
        <p class="d-note">Nog geen gesprekken. Stel een vraag op een kavelpagina, of wacht op een bericht van een koper.</p>
    @endforelse
</nav>
