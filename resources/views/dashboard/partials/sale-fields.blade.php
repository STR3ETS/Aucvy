@php
    $tz = config('aucvy.display_timezone');
    $local = fn ($value, string $format) => $value ? $value->timezone($tz)->format($format) : '';
    $input = 'w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface px-3 text-sm';
@endphp

<div class="grid gap-4">
    <label class="grid gap-1 text-sm">
        <span class="font-bold">Naam van de veiling</span>
        <input type="text" name="title" value="{{ old('title', $sale?->title) }}" required minlength="3" maxlength="150" placeholder="Faillissementsveiling Restaurant De Molen" class="{{ $input }}" style="min-height:42px">
    </label>
    <label class="grid gap-1 text-sm">
        <span class="font-bold">Omschrijving</span>
        <textarea name="description" rows="3" maxlength="4000" placeholder="Wat wordt er geveild, waarom, en voor wie is het interessant." class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm">{{ old('description', $sale?->description) }}</textarea>
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Eerste sluiting</span>
            <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $local($sale?->ends_at, 'Y-m-d\TH:i')) }}" required class="{{ $input }}" style="min-height:42px">
            <span class="text-xs text-aj-muted">De kavels sluiten na elkaar, in de volgorde van de lijst.</span>
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Tussentijd per kavel</span>
            <select name="closing_gap_seconds" class="{{ $input }}" style="min-height:42px">
                @foreach ([60 => '1 minuut', 120 => '2 minuten', 300 => '5 minuten', 600 => '10 minuten'] as $seconds => $label)
                    <option value="{{ $seconds }}" @selected((int) old('closing_gap_seconds', $sale?->closing_gap_seconds ?? 120) === $seconds)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="text-xs text-aj-muted">Een bod in de laatste minuut verlengt alleen die kavel.</span>
        </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Bezichtiging</span>
            <input type="datetime-local" name="viewing_at" value="{{ old('viewing_at', $local($sale?->viewing_at, 'Y-m-d\TH:i')) }}" class="{{ $input }}" style="min-height:42px">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Toelichting bezichtiging</span>
            <input type="text" name="viewing_note" value="{{ old('viewing_note', $sale?->viewing_note) }}" maxlength="500" placeholder="Op afspraak, adres volgt per mail" class="{{ $input }}" style="min-height:42px">
        </label>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Ophalen vanaf</span>
            <input type="date" name="pickup_from" value="{{ old('pickup_from', $local($sale?->pickup_from, 'Y-m-d')) }}" class="{{ $input }}" style="min-height:42px">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Ophalen tot</span>
            <input type="date" name="pickup_until" value="{{ old('pickup_until', $local($sale?->pickup_until, 'Y-m-d')) }}" class="{{ $input }}" style="min-height:42px">
        </label>
        <label class="grid gap-1 text-sm">
            <span class="font-bold">Toelichting ophalen</span>
            <input type="text" name="pickup_note" value="{{ old('pickup_note', $sale?->pickup_note) }}" maxlength="500" placeholder="Zelf demonteren, heftruck aanwezig" class="{{ $input }}" style="min-height:42px">
        </label>
    </div>
    <label class="grid gap-1 text-sm">
        <span class="font-bold">Eigen voorwaarden</span>
        <textarea name="terms" rows="4" maxlength="8000" placeholder="Bijvoorbeeld: betaling binnen 3 werkdagen, ophalen op afspraak, geen retour." class="w-full rounded-aj-sm border border-aj-border-strong bg-aj-surface p-3 text-sm">{{ old('terms', $sale?->terms) }}</textarea>
        <span class="text-xs text-aj-muted">Staan op de veilingpagina naast de voorwaarden van Aucvy. De juridische teksten van het platform gaan hier niet van af.</span>
    </label>
</div>
