{{--
    Korte vraag en gesprek vanaf de kavelpagina (briefing nieuwe functies),
    plus de vragen en antwoorden die de verkoper bij de kavel zette. Opmaak:
    het blok "Vraag het de verkoper" uit het ontwerp van november, met twee
    voorbeeldvragen die het veld vullen.
--}}
@php
    $viewer = auth()->user();
    $isSeller = $viewer !== null && $lot->isManagedBy($viewer);
    $answers = $lot->publicAnswers;
    $suggestions = array_values(array_filter([
        $lot->viewing_available ? 'Kan ik eerst komen kijken?' : 'Is demontage inbegrepen?',
        $lot->pickup_only ? 'Wanneer kan ik ophalen?' : 'Wat kost transport naar mij?',
    ]));
@endphp

@unless ($isSeller)
    <section class="d-side-card" aria-labelledby="vraag-kop"
             x-data="{ kind: 'question', body: @js(old('body', '')) }">
        <h3 id="vraag-kop"><x-v4.icon name="message-circle" :size="18" stroke="2.2" /> Vraag het de verkoper</h3>

        @auth
            @if ($errors->hasAny(['verification', 'lot']))
                <p class="d-soft is-warn" style="margin:0">
                    {{ $errors->first('verification') ?: $errors->first('lot') }}
                    @if ($errors->has('verification')) <a href="{{ route('verification.overview') }}" class="d-link">Naar verificatie</a> @endif
                </p>
            @endif
            <form method="POST" action="{{ route('lots.ask', $lot) }}" class="d-stack-tight">
                @csrf
                <input type="hidden" name="kind" :value="kind">
                <label>
                    <span class="sr-only">Je vraag aan de verkoper</span>
                    <textarea name="body" rows="2" required minlength="2" maxlength="3000" placeholder="Stel een korte vraag" class="d-textarea" x-model="body"></textarea>
                </label>
                @error('body')<p class="d-hint is-warn">{{ $message }}</p>@enderror
                <div class="d-chips" style="gap:6px">
                    @foreach ($suggestions as $suggestion)
                        <button type="button" class="d-suggest" @click="body = @js($suggestion)">{{ $suggestion }}</button>
                    @endforeach
                </div>
                <template x-if="kind === 'question'">
                    <fieldset style="border:0;padding:0;margin:0">
                        <legend class="d-hint" style="margin:0 0 4px">Waar gaat je vraag over?</legend>
                        <div class="d-chips" style="gap:6px">
                            @foreach (\App\Models\Conversation::PARTS as $part)
                                <label class="d-chip d-chip-sm" style="cursor:pointer;min-height:36px" :class="$el.querySelector('input').checked && 'is-on'">
                                    <input type="radio" name="lot_part" value="{{ $part }}" class="sr-only" @checked(old('lot_part', request()->query('vraag', 'specificaties')) === $part) @change="$el.closest('.d-chips').querySelectorAll('label').forEach(l => l.classList.toggle('is-on', l.querySelector('input').checked))">
                                    {{ __('messaging.parts.'.$part) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </template>
                <button type="submit" class="d-btn d-btn-wide" x-text="kind === 'question' ? 'Vraag versturen' : 'Bericht versturen'">Vraag versturen</button>
                <button type="button" class="d-link" style="text-align:left" @click="kind = kind === 'question' ? 'chat' : 'question'" x-text="kind === 'question' ? 'Liever een privébericht sturen?' : 'Liever een openbare vraag stellen?'"></button>
            </form>
            <p class="d-hint" style="margin:0">Het gesprek blijft aan deze kavel gekoppeld. Ivy bewaakt het en een medewerker van Aucvy kan meekijken. {{ __('messaging.notices.no_contact') }}</p>
        @else
            <p class="d-note">{{ __('messaging.notices.names') }} Gesprekken worden gecontroleerd op afspraken buiten Aucvy om.</p>
            <a href="{{ route('login') }}" class="d-btn d-btn-wide">Inloggen om te vragen</a>
        @endauth
    </section>
@endunless
