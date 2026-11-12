@use('App\Enums\LotStatus')
@use('App\Enums\SaleType')
@use('App\Support\Money\Money')

{{--
    Eén regel in een tabel op Mijn kavels. Welke cellen erin staan bepaalt de
    tabel ($columns): een concept heeft geen bod en geen waarde, een verkochte
    kavel geen kijkers meer. De waarde volgens Atlas staat erbij zodra er een
    schatting is; de uitkomst (het bod naast die waarde, met een kleine ring)
    alleen als de schatting scherp genoeg is.

    Voor een verkoper is een bod boven de waarde goed nieuws: groen. Erbinnen
    is blauw, eronder oranje. Bij een koper is dat andersom.
--}}
@php
    $group = $groupOf($lot->status);
    $isDraft = $lot->status === LotStatus::Draft;
    $siteUrl = $lot->status->isPublic() ? route('lots.show', $lot) : null;
    $dossierUrl = $isDraft ? route('sell', ['concept' => $lot->id]) : route('dashboard.lots.show', $lot);
    $auction = $lot->auction;
    $interest = in_array('interest', $columns, true) ? \App\Lots\Interest\Interest::forBuyer($lot) : null;
    // Wat het is: het kavelnummer en de verkoopvorm; een concept heeft nog geen nummer dat ertoe doet.
    $kind = $isDraft ? 'Nog niet gepubliceerd' : 'Kavel #'.$lot->public_id.($lot->sale_type !== null ? ' · '.$lot->sale_type->label() : '');
    // Wanneer er iets gebeurt: de sluittijd, de uitbetaling of wanneer je er het laatst aan werkte.
    $moment = match (true) {
        $isDraft => 'bewerkt '.$lot->updated_at->timezone($displayTimezone)->diffForHumans(),
        $auction !== null && ! $auction->isClosed() => $auction->closesText(),
        $auction !== null && $lot->order?->payout !== null => 'uitbetaling: '.mb_strtolower($lot->order->payout->status->label()),
        $auction !== null => 'gesloten op '.rtrim($auction->ends_at->timezone($displayTimezone)->isoFormat('D MMM'), '.'),
        default => 'nog geen veiling gepland',
    };
    [$amountLabel, $amountCents] = match (true) {
        $isDraft => ['', null],
        $lot->status === LotStatus::Sold && $lot->order !== null => ['Verkocht voor', $lot->order->hammer_cents],
        $auction !== null && $lot->sale_type === SaleType::Dutch => ['Prijs nu', $lot->currentPriceCents()],
        $auction !== null && $lot->sale_type === SaleType::BuyNow => ['Vaste prijs', $lot->currentPriceCents()],
        $auction !== null => ['Huidig bod', $lot->currentPriceCents()],
        default => ['', null],
    };
    $complete = (int) $lot->completeness_score;
    $whole = fn (int $cents): string => '€ '.number_format(intdiv($cents, 100), 0, ',', '.');
    $hasValue = $lot->hasValueEstimate();
    // De uitkomst: het bedrag naast de waarde-indicatie, alleen bij een scherpe schatting en een bedrag.
    $verdict = null;
    if ($amountCents !== null && $lot->mayShowValueComparison() && $lot->value_max_cents > 0) {
        $verdict = match (true) {
            $amountCents > $lot->value_max_cents => ['below', 'Boven de waarde-indicatie'],
            $amountCents < $lot->value_min_cents => ['above', (int) round(100 - $amountCents / max(1, $lot->value_min_cents) * 100).'% onder de waarde-indicatie'],
            default => ['within', 'Binnen de waarde-indicatie'],
        };
        $verdictPercent = (int) round($amountCents / $lot->value_max_cents * 100);
    }
@endphp

<article class="d-lotrow" x-show="ids[{{ $lot->id }}]" @unless ($shown->has($lot->id)) x-cloak @endunless>
    <div class="d-lotrow-lot">
        <label class="pick" x-show="selecting" x-cloak>
            <input type="checkbox" name="lots[]" value="{{ $lot->id }}" x-model="selected" aria-label="Selecteer {{ $lot->title }}">
        </label>
        <a class="thumb" href="{{ $dossierUrl }}" tabindex="-1" aria-hidden="true">
            @if ($lot->cover_path)
                <img src="{{ $lot->cover_path }}" alt="" loading="lazy">
            @else
                <x-v4.icon name="image" :size="20" stroke="1.6" />
            @endif
        </a>
        <div class="text">
            <strong><a href="{{ $dossierUrl }}">{{ $lot->title ?: 'Concept zonder titel' }}</a></strong>
            <span class="d-lotrow-tags">
                <span class="d-tag {{ $tagTone[$group] }}">{{ $lot->status->label() }}</span>
                @if ($lot->is_hot && ! $isDraft)
                    <span class="d-tag tone-hot"><x-v4.icon name="flame" :size="12" stroke="2.4" /> Hot</span>
                @endif
                <span class="d-bid-meta">{{ $kind }}</span>
            </span>
        </div>
    </div>

    <div class="d-lotrow-facts">
        @if (in_array('complete', $columns, true))
            <span class="cell is-interest">
                <span class="d-ring is-sm is-{{ $complete >= 80 ? 'below' : 'within' }}" role="img" aria-label="{{ $complete }}% compleet">
                    <svg viewBox="0 0 100 100" aria-hidden="true">
                        <circle class="track" cx="50" cy="50" r="38"></circle>
                        <circle class="value" cx="50" cy="50" r="38" pathLength="100" stroke-dasharray="{{ min(100, max(4, $complete)) }} 100"></circle>
                    </svg>
                </span>
                <span><b class="tabular">{{ $complete }}%</b> compleet</span>
            </span>
        @endif

        @if (in_array('interest', $columns, true))
            <span class="cell is-interest">
                <span class="d-counts">
                    <span title="kijkers"><x-v4.icon name="eye" :size="14" stroke="2" />{{ number_format($interest['viewers'], 0, ',', '.') }}</span>
                    <span title="keer opgeslagen"><x-v4.icon name="heart" :size="14" stroke="2" />{{ $interest['saved'] }}</span>
                    <span title="bieders"><x-v4.icon name="gavel" :size="14" stroke="2" />{{ (int) ($auction?->bidder_count ?? 0) }}</span>
                </span>
            </span>
        @endif

        @if (in_array('amount', $columns, true))
            <span class="cell is-amount">
                @if ($amountCents !== null)
                    <b class="tabular">{{ Money::format($amountCents) }}</b>
                    <small>{{ $amountLabel }}</small>
                @endif
            </span>
        @endif

        @if (in_array('value', $columns, true))
            <span class="cell is-value">
                <span class="label">Waarde volgens Atlas</span>
                @if ($hasValue)
                    <b class="tabular">{{ $whole($lot->value_min_cents) }} – {{ $whole($lot->value_max_cents) }}</b>
                @else
                    <span class="d-muted">nog geen indicatie</span>
                @endif
            </span>
        @endif

        @if (in_array('verdict', $columns, true))
            <span class="cell d-bought-verdict{{ $verdict !== null ? ' is-'.$verdict[0] : '' }}">
                @if ($verdict !== null)
                    <span class="d-ring is-sm is-{{ $verdict[0] }}" role="img" aria-label="{{ $verdictPercent }}% van de bovenkant van de waarde-indicatie">
                        <svg viewBox="0 0 100 100" aria-hidden="true">
                            <circle class="track" cx="50" cy="50" r="38"></circle>
                            <circle class="value" cx="50" cy="50" r="38" pathLength="100" stroke-dasharray="{{ min(100, max(4, $verdictPercent)) }} 100"></circle>
                        </svg>
                    </span>
                    <span>{{ $verdict[1] }}</span>
                @endif
            </span>
        @endif

        <span class="cell is-moment">{{ ucfirst($moment) }}</span>
    </div>

    {{-- Acties als pictogram, met de naam bij aanwijzen en voor de schermlezer. --}}
    <div class="d-lotrow-actions">
        @if ($siteUrl !== null)
            <a href="{{ $siteUrl }}" class="d-iconbtn" data-tip="Bekijk op de site" aria-label="{{ $lot->title }} bekijken op de site"><x-v4.icon name="eye" :size="17" stroke="2" /></a>
        @endif
        @if ($isDraft)
            <a href="{{ $dossierUrl }}" class="d-iconbtn" data-tip="{{ __('dashboard.actions.continue_draft') }}" aria-label="{{ __('dashboard.actions.continue_draft') }}: {{ $lot->title ?: 'concept zonder titel' }}"><x-v4.icon name="pencil" :size="17" stroke="2" /></a>
            {{-- De rij staat in het bulkformulier: deze knop stuurt dat formulier naar de eigen route, met het token erin. --}}
            <button type="submit" class="d-iconbtn is-danger" data-tip="Concept verwijderen" aria-label="Concept verwijderen: {{ $lot->title ?: 'concept zonder titel' }}"
                    formaction="{{ route('dashboard.lots.destroy-draft', $lot) }}" formmethod="POST"
                    onclick="return confirm('Dit concept verwijderen? Dat kan niet ongedaan worden gemaakt.')"><x-v4.icon name="trash" :size="17" stroke="2" /></button>
        @elseif (in_array($lot->status, [LotStatus::Unsold, LotStatus::Cancelled], true))
            <button type="submit" class="d-iconbtn" data-tip="Opnieuw plaatsen" aria-label="{{ $lot->title }} opnieuw plaatsen" formaction="{{ route('dashboard.lots.duplicate', ['lot' => $lot->id, 'relist' => 1]) }}" formmethod="POST"><x-v4.icon name="refresh" :size="17" stroke="2" /></button>
        @elseif (in_array($lot->status, [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing, LotStatus::Paused], true))
            <a href="{{ route('dashboard.lots.edit', $lot) }}" class="d-iconbtn" data-tip="{{ $lot->status === LotStatus::Paused ? 'Hervatten' : 'Bewerken' }}" aria-label="{{ $lot->title }} {{ $lot->status === LotStatus::Paused ? 'hervatten' : 'bewerken' }}"><x-v4.icon :name="$lot->status === LotStatus::Paused ? 'play' : 'pencil'" :size="17" stroke="2" /></a>
        @endif
    </div>
</article>
