{{--
    De lijst zonder geopend gesprek. Wie gesprekken heeft komt hier alleen
    via "Oudere gesprekken" of, op de telefoon, via de terugknop in een
    gesprek: de gewone ingang opent meteen het gesprek dat aandacht vraagt.
--}}
<x-layouts.dashboard title="Berichten" eyebrow="Berichten" intro="Elk gesprek hoort bij één kavel. Vragen, mail en status staan bij elkaar.">
    @if (session('status'))
        <p class="d-soft is-ok" style="margin:0 0 14px">{{ session('status') }}</p>
    @endif

    @if ($conversations->isEmpty())
        <x-ui.empty-state
            title="Nog geen gesprekken"
            body="Stel een vraag op een kavelpagina, of wacht op een bericht van een koper. Elk gesprek blijft aan de kavel gekoppeld.">
            <x-slot:action>
                <x-ui.button :href="route('lots.index')">Naar de veilingen</x-ui.button>
            </x-slot:action>
        </x-ui.empty-state>
    @else
        <div class="d-msg-layout is-list">
            @include('dashboard.messages.partials.list', ['items' => $conversations, 'current' => null, 'user' => $user])

            <section class="d-thread is-empty" aria-label="Gesprek">
                <div class="d-thread-pick">
                    <x-v4.icon name="message-circle" :size="28" stroke="1.8" />
                    <p class="d-h3">Kies een gesprek</p>
                    <p class="d-sub">{{ $unreadCount > 0 ? $unreadCount.' '.($unreadCount === 1 ? 'gesprek heeft' : 'gesprekken hebben').' een nieuw bericht.' : 'Alles is gelezen. Nieuwe berichten verschijnen hier en bij je meldingen.' }}</p>
                </div>
            </section>

            <aside class="d-card d-lotside" aria-label="Zo werkt het">
                <h2 class="d-h2">Zo werkt het</h2>
                <p class="d-sub" style="margin-top:8px">Elk gesprek blijft aan de kavel gekoppeld. Ivy bewaakt het en een medewerker van Aucvy kan meekijken. {{ __('messaging.notices.no_contact') }}</p>
            </aside>
        </div>
        <div class="mt-4">{{ $conversations->links() }}</div>
    @endif
</x-layouts.dashboard>
