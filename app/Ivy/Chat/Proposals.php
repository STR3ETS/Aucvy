<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Auctions\Support\BidGuards;
use App\Enums\SaleType;
use App\Models\IvyActionProposal;
use App\Models\IvyConversation;
use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;
use App\Support\Money\Money;
use App\Support\Money\OrderCosts;
use Carbon\CarbonInterface;

/**
 * Voorstellen van Ivy (Ivy-briefing hfst. 7): een voorstel doet niets tot
 * de klant bevestigt op het bevestigingsscherm met het actuele bedrag en
 * alle kosten. Geldig tot de veiling sluit; een bod dat te laag is
 * geworden wordt een nieuw voorstel met de eerstvolgende stap, nooit
 * automatisch opgehoogd.
 */
final class Proposals
{
    public function __construct(private readonly BidGuards $guards) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(string $type, User $user, Lot $lot, array $input, ?IvyConversation $conversation = null): IvyActionProposal
    {
        $lot->loadMissing('auction');
        $auction = $lot->auction;

        $amount = match ($type) {
            IvyActionProposal::BID => max((int) ($input['amount_cents'] ?? 0), $auction ? $this->guards->minimumBid($auction) : 0),
            IvyActionProposal::AUTO_BID => (int) ($input['limit_cents'] ?? 0),
            IvyActionProposal::DUTCH_ACCEPT, IvyActionProposal::BUY_NOW => $lot->currentPriceCents(),
            IvyActionProposal::RESERVE_CHANGE => (int) ($input['reserve_cents'] ?? 0),
            default => null,
        };

        $proposal = IvyActionProposal::create([
            'conversation_id' => $conversation?->id,
            'user_id' => $user->id,
            'type' => $type,
            'lot_id' => $lot->id,
            'payload' => $input,
            'amount_cents' => $amount,
            'expected_current_cents' => $auction?->current_price_cents,
            'expected_ends_at' => $auction?->ends_at,
            'status' => IvyActionProposal::PROPOSED,
            'expires_at' => $auction?->ends_at ?? now()->addDay(),
        ]);

        Audit::record('ivy.proposal_created', $lot, $user, after: ['proposal_id' => $proposal->id, 'type' => $type, 'amount_cents' => $amount]);

        return $proposal;
    }

    /**
     * Het scherm haalt actuele prijs, kosten en eindtijd op; de stand gaat
     * naar "shown" en bij een verschoven prijs naar "price_changed".
     *
     * @return array{proposal: IvyActionProposal, lot: Lot, current_cents: int|null, ends_at: CarbonInterface|null, price_changed: bool, still_valid: bool, minimum_cents: int|null, costs: OrderCosts|null, closed: bool}
     */
    public function show(IvyActionProposal $proposal): array
    {
        $lot = $proposal->lot()->with('auction')->firstOrFail();
        $auction = $lot->auction;
        $current = $auction?->current_price_cents;
        $closed = $auction === null || $auction->isClosed() || ! $lot->status->isPublic();
        $priceChanged = $proposal->expected_current_cents !== null && $current !== null && $current !== $proposal->expected_current_cents;
        $minimum = $auction !== null && ! $closed ? $this->guards->minimumBid($auction) : null;
        $stillValid = $proposal->type !== IvyActionProposal::BID || ($minimum !== null && $proposal->amount_cents >= $minimum);

        if ($proposal->isOpen()) {
            $proposal->forceFill([
                'status' => $closed ? IvyActionProposal::EXPIRED : ($priceChanged ? IvyActionProposal::PRICE_CHANGED : IvyActionProposal::SHOWN),
                'expected_current_cents' => $current,
                'expected_ends_at' => $auction?->ends_at,
            ])->save();
        }

        $amountForCosts = match ($proposal->type) {
            IvyActionProposal::BID, IvyActionProposal::AUTO_BID => (int) $proposal->amount_cents,
            default => $lot->currentPriceCents(),
        };

        return [
            'proposal' => $proposal,
            'lot' => $lot,
            'current_cents' => $current,
            'ends_at' => $auction?->ends_at,
            'price_changed' => $priceChanged,
            'still_valid' => $stillValid,
            'minimum_cents' => $minimum,
            'costs' => $auction !== null && in_array($proposal->type, [IvyActionProposal::BID, IvyActionProposal::AUTO_BID, IvyActionProposal::DUTCH_ACCEPT, IvyActionProposal::BUY_NOW], true) ? Money::forOrder($lot, $amountForCosts, $lot->transport_from_cents ?? 0) : null,
            'closed' => $closed,
        ];
    }

    public function reject(IvyActionProposal $proposal, string $status = IvyActionProposal::REJECTED): void
    {
        if ($proposal->isOpen()) {
            $proposal->forceFill(['status' => $status])->save();
        }
    }

    public function describe(IvyActionProposal $proposal): string
    {
        $lot = $proposal->lot;
        $title = $lot?->title ?? 'de kavel';

        return match ($proposal->type) {
            IvyActionProposal::BID => 'Voorstel: bod van '.Money::format((int) $proposal->amount_cents).' op "'.$title.'".',
            IvyActionProposal::AUTO_BID => 'Voorstel: automatisch bieden tot '.Money::format((int) $proposal->amount_cents).' op "'.$title.'". Boven die limiet bied ik nooit.',
            IvyActionProposal::DUTCH_ACCEPT => 'Voorstel: afmijnen op de prijs van nu voor "'.$title.'".',
            IvyActionProposal::BUY_NOW => 'Voorstel: koop direct van "'.$title.'".',
            IvyActionProposal::RESERVE_CHANGE => 'Voorstel: reserve verlagen naar '.Money::format((int) $proposal->amount_cents).' voor "'.$title.'".',
            IvyActionProposal::PUBLISH => 'Voorstel: "'.$title.'" publiceren.',
            default => 'Voorstel klaargezet.',
        };
    }

    public function expireOpen(): int
    {
        $count = 0;

        $open = IvyActionProposal::query()->whereIn('status', [IvyActionProposal::PROPOSED, IvyActionProposal::SHOWN, IvyActionProposal::PRICE_CHANGED])
            ->where(fn ($query) => $query->where('expires_at', '<=', now())->orWhereHas('lot.auction', fn ($auction) => $auction->whereNotNull('closed_at')))
            ->get();

        foreach ($open as $proposal) {
            $proposal->forceFill(['status' => IvyActionProposal::EXPIRED])->save();
            $count++;
        }

        return $count;
    }

    public function isDutchAllowed(Lot $lot): bool
    {
        return $lot->sale_type === SaleType::Dutch && IvyFlags::on('auctions.dutch');
    }
}
