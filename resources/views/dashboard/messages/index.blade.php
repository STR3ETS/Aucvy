<x-layouts.dashboard title="Berichten" eyebrow="Berichten" intro="Elk gesprek hoort bij één kavel. Vragen, mail en status staan bij elkaar.">
    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif

    @if ($conversations->isEmpty())
        <p class="feed-empty" style="margin:0">Nog geen gesprekken. Stel een vraag op een kavelpagina, of wacht op een bericht van een koper.</p>
    @else
        <div class="d-msg-layout">
            @include('dashboard.messages.partials.list', ['items' => $conversations, 'current' => null, 'user' => $user])

            <section class="d-thread" aria-label="Gesprek" style="align-items:center;justify-content:center;min-height:320px">
                <div style="text-align:center;padding:32px;max-width:36ch">
                    <x-v4.icon name="message-circle" :size="28" stroke="1.8" style="color:#2f6bff" />
                    <p class="d-h3" style="margin-top:10px">Kies een gesprek</p>
                    <p class="d-sub">{{ $unreadCount > 0 ? $unreadCount.' '.($unreadCount === 1 ? 'gesprek heeft' : 'gesprekken hebben').' een nieuw bericht.' : 'Alles is gelezen. Nieuwe berichten verschijnen hier en bij je meldingen.' }}</p>
                </div>
            </section>

            <aside class="d-card" aria-label="Zo werkt het">
                <h2 class="d-h3">Zo werkt het</h2>
                <p class="d-sub">Elk gesprek blijft aan de kavel gekoppeld. Ivy bewaakt het en een medewerker van Aucvy kan meekijken. {{ __('messaging.notices.no_contact') }}</p>
            </aside>
        </div>
        <div class="mt-4">{{ $conversations->links() }}</div>
    @endif
</x-layouts.dashboard>
