{{--
    De ontvanger van een gedeelde link: de notitie van de deler boven de kavel,
    zonder account, en één reactie terug. Nooit het bod of de biednaam van de
    deler.
--}}
@use('App\Models\LotShare')

<section class="mb-5 rounded-aj-s border border-aj-blue/30 bg-aj-blue/5 p-4" aria-labelledby="gedeeld-kop">
    <h2 id="gedeeld-kop" class="text-sm font-extrabold">Iemand deelde deze kavel met je</h2>
    @if ($share->note)
        <p class="mt-1 text-sm">"{{ $share->note }}"</p>
    @endif
    @if (session('status'))
        <p class="mt-2 text-sm font-semibold text-aj-success-text">{{ session('status') }}</p>
    @elseif ($share->reaction === null && ! (auth()->check() && auth()->id() === $share->user_id))
        <form method="POST" action="{{ route('lots.shared.react', [$lot, $share->token]) }}" class="mt-3 flex flex-wrap items-center gap-2" x-data="{ text: false }">
            @csrf
            <button type="submit" name="reaction" value="{{ LotShare::INTERESTED }}" class="btn btn-primary" style="height:36px;font-size:13px">Interessant</button>
            <button type="submit" name="reaction" value="{{ LotShare::NOT_INTERESTED }}" class="btn btn-ghost" style="height:36px;font-size:13px">Niet interessant</button>
            <button type="button" class="btn btn-ghost" style="height:36px;font-size:13px" @click="text = ! text">Korte reactie</button>
            <div x-show="text" x-cloak class="flex w-full gap-2">
                <input type="text" name="text" maxlength="300" placeholder="Je reactie, in één zin" class="flex-1 rounded-aj-sm border border-aj-border-strong bg-aj-surface px-2 text-sm" style="min-height:36px">
                <button type="submit" name="reaction" value="{{ LotShare::TEXT }}" class="btn btn-dark" style="height:36px;font-size:13px">Versturen</button>
            </div>
            @error('text')<p class="w-full text-xs text-aj-error">{{ $message }}</p>@enderror
        </form>
        <p class="mt-2 text-[11px] text-aj-muted">Je reactie gaat alleen naar degene die de kavel deelde. Bieden kan met een eigen account.</p>
    @elseif ($share->reaction !== null)
        <p class="mt-2 text-xs text-aj-muted">Er is al gereageerd op deze link.</p>
    @endif
</section>
