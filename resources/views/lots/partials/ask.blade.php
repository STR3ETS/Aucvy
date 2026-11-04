{{--
    Korte vraag en gesprek vanaf de kavelpagina (briefing nieuwe functies),
    plus de vragen en antwoorden die de verkoper bij de kavel zette.
--}}
@php
    $viewer = auth()->user();
    $isSeller = $viewer !== null && $lot->isManagedBy($viewer);
    $answers = $lot->publicAnswers;
@endphp

@if ($answers->isNotEmpty())
    <section id="vragen" aria-labelledby="vragen-kop">
        <h2 id="vragen-kop" class="text-lg font-extrabold">Vragen en antwoorden</h2>
        <p class="mt-1 text-xs text-aj-muted">Antwoorden van de verkoper die voor alle bieders gelden. De naam van de vrager wordt niet getoond.</p>
        <ul class="mt-2 space-y-2">
            @foreach ($answers as $answer)
                <li class="rounded-aj-sm border border-aj-border bg-aj-surface p-3 text-sm">
                    <p class="font-bold">{{ $answer->question }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $answer->answer }}</p>
                    <p class="mt-1 text-xs text-aj-muted">@if ($answer->partLabel())Over: {{ $answer->partLabel() }} · @endif antwoord van de verkoper</p>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@unless ($isSeller)
    <section class="rounded-aj-s border border-aj-border bg-aj-surface p-4" aria-labelledby="vraag-kop"
             x-data="{ open: {{ $errors->hasAny(['body', 'kind', 'lot_part', 'verification', 'lot']) ? 'true' : 'false' }}, kind: 'question' }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 id="vraag-kop" class="text-sm font-extrabold">Vraag aan de verkoper</h2>
                <p class="text-xs text-aj-muted">{{ __('messaging.notices.names') }} Gesprekken worden gecontroleerd op afspraken buiten Aucvy om.</p>
            </div>
            @auth
                <div class="flex gap-2">
                    <button type="button" class="btn btn-primary" style="height:38px;font-size:13px" @click="open = true; kind = 'question'">Korte vraag</button>
                    <button type="button" class="btn btn-ghost" style="height:38px;font-size:13px" @click="open = true; kind = 'chat'">Stuur bericht</button>
                </div>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary" style="height:38px;font-size:13px">Inloggen om te vragen</a>
            @endauth
        </div>

        @auth
            @if ($errors->hasAny(['verification', 'lot']))
                <p class="mt-3 rounded-aj-sm border border-aj-warn/40 bg-aj-warn/5 p-3 text-xs">
                    {{ $errors->first('verification') ?: $errors->first('lot') }}
                    @if ($errors->has('verification')) <a href="{{ route('verification.overview') }}" class="font-bold underline">Naar verificatie</a> @endif
                </p>
            @endif
            <form method="POST" action="{{ route('lots.ask', $lot) }}" x-show="open" x-cloak class="mt-4 space-y-3">
                @csrf
                <input type="hidden" name="kind" :value="kind">
                <template x-if="kind === 'question'">
                    <fieldset>
                        <legend class="text-sm font-bold">Waar gaat je vraag over?</legend>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach (\App\Models\Conversation::PARTS as $part)
                                <label class="cursor-pointer rounded-full border border-aj-border px-3 py-1 text-xs font-bold has-[:checked]:bg-aj-navy has-[:checked]:text-white">
                                    <input type="radio" name="lot_part" value="{{ $part }}" class="sr-only" @checked(old('lot_part', 'specificaties') === $part)>
                                    {{ __('messaging.parts.'.$part) }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </template>
                <label class="block">
                    <span class="text-sm font-bold" x-text="kind === 'question' ? 'Je vraag' : 'Je bericht'"></span>
                    <textarea name="body" rows="3" required minlength="2" maxlength="3000" class="mt-1 w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm">{{ old('body') }}</textarea>
                </label>
                @error('body')<p class="text-xs text-aj-error">{{ $message }}</p>@enderror
                <p class="text-xs text-aj-muted">
                    <span x-show="kind === 'question'">{{ __('messaging.notices.question_public') }}</span>
                    {{ __('messaging.notices.no_contact') }}
                </p>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary" style="height:40px;font-size:13px" x-text="kind === 'question' ? 'Vraag versturen' : 'Bericht versturen'"></button>
                    <button type="button" class="btn btn-ghost" style="height:40px;font-size:13px" @click="open = false">Annuleren</button>
                </div>
            </form>
        @endauth
    </section>
@endunless
