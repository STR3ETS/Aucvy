<?php

declare(strict_types=1);

namespace App\Auctions\Support;

use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Exceptions\BidRejectionReason;
use App\Enums\BidStatus;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Deposit;
use App\Models\User;

/**
 * De voorwaarden waaraan elk bod moet voldoen.
 *
 * Apart van de acties, zodat een handmatig bod en een automatisch maximum
 * dezelfde regels erven in plaats van een kopie. Zou je ze dupliceren, dan is
 * de vraag niet óf maar wannéér er eentje uit de pas gaat lopen - en dan is er
 * één route waarlangs een niet-geverifieerde gebruiker toch kan bieden.
 */
final class BidGuards
{
    public function ensureOpen(Auction $auction): void
    {
        if ($auction->isClosed() || ! $auction->lot->status->acceptsBids()) {
            throw BidRejected::because(BidRejectionReason::AuctionNotOpen);
        }

        // Op de seconde van sluiten is het voorbij.
        if ($auction->ends_at->getTimestamp() <= now()->getTimestamp()) {
            throw BidRejected::because(BidRejectionReason::AuctionEnded);
        }
    }

    public function ensureNotSeller(Auction $auction, User $user): void
    {
        if ($auction->lot->seller_id === $user->id) {
            throw BidRejected::because(BidRejectionReason::OwnLot);
        }

        /*
         | Ook accounts van hetzelfde bedrijf. Shill bidding is een
         | reputatierisico dat je niet achteraf repareert (hfst. 6).
         */
        if ($auction->lot->company_id !== null && $auction->lot->company_id === $user->company_id) {
            throw BidRejected::because(BidRejectionReason::SameCompany);
        }
    }

    /**
     * Verificatie blokkeert uitsluitend het bieden - niet het rondkijken,
     * zoeken of volgen. De gate zit hier hard in de motor en niet alleen in de
     * UI (hfst. 13.6).
     */
    public function ensureVerified(User $user): void
    {
        if (! $user->kyc_status->allowsBidding()) {
            throw BidRejected::because(BidRejectionReason::NotVerified);
        }
    }

    /**
     * Boven een drempelbedrag is een reservering nodig vóór het bod.
     *
     * De enige echte bescherming tegen een winnende bieder die niet betaalt.
     * De kavelpagina meldt dit vooraf, niet pas bij het klikken (hfst. 9).
     */
    public function ensureDepositCovers(Auction $auction, User $user, int $amountCents): void
    {
        if ($amountCents <= (int) config('aucvy.deposit.threshold_cents')) {
            return;
        }

        $covered = Deposit::query()
            ->where('auction_id', $auction->id)
            ->where('user_id', $user->id)
            ->covering()
            ->exists();

        if (! $covered) {
            throw BidRejected::because(BidRejectionReason::DepositRequired);
        }
    }

    /**
     * De handeling moet bij de verkoopvorm van de kavel passen.
     *
     * Afmijnen op een gewone veiling of bieden op een afslag is geen
     * gebruikersfout maar een verkeerd aangeroepen endpoint - en dat hoort te
     * knallen in plaats van iets plausibels te doen.
     */
    public function ensureSaleType(Auction $auction, SaleType $expected): void
    {
        if ($auction->lot->sale_type !== $expected) {
            throw BidRejected::because(BidRejectionReason::SaleTypeMismatch);
        }
    }

    /**
     * Tegen jezelf bieden drijft alleen je eigen prijs op. Wie hoger wil, stelt
     * een maximum in - daar is de tweede knop voor.
     */
    public function ensureNotAlreadyLeading(Auction $auction, User $user): void
    {
        if ($this->isLeading($auction, $user)) {
            throw BidRejected::because(BidRejectionReason::AlreadyHighest);
        }
    }

    public function ensureMeetsMinimum(Auction $auction, int $amountCents): void
    {
        $minimum = $this->minimumBid($auction);

        if ($amountCents < $minimum) {
            throw BidRejected::tooLow($minimum, $auction->current_price_cents);
        }
    }

    public function minimumBid(Auction $auction): int
    {
        return IncrementTable::forAuction($auction)->minimumBid(
            currentPriceCents: $auction->current_price_cents,
            hasBids: $auction->bid_count > 0,
            startPriceCents: $auction->start_price_cents,
        );
    }

    public function isLeading(Auction $auction, User $user): bool
    {
        return Bid::query()
            ->where('auction_id', $auction->id)
            ->where('status', BidStatus::Winning)
            ->where('user_id', $user->id)
            ->exists();
    }
}
