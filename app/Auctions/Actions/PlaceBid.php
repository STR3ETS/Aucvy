<?php

declare(strict_types=1);

namespace App\Auctions\Actions;

use App\Auctions\Support\BidGuards;
use App\Auctions\Support\BidWriter;
use App\Auctions\Support\ProxyResolver;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * De enige plek waar een bod ontstaat.
 *
 * Niet in een controller, niet in een observer, niet in een seeder, niet in
 * Tinker. Denk je een tweede plek nodig te hebben, dan is het antwoord een
 * extra Action die intern deze aanroept (hfst. 3).
 *
 * Een handmatig bod is een hard bod: win je ermee, dan is de prijs het bedrag
 * dat je hebt ingevuld. Wil je alleen betalen wat nodig is om voor te blijven,
 * dan is dat PlaceAutoBid.
 *
 * De volgorde hieronder is niet willekeurig:
 *
 *   1. idempotentie      een retry mag geen tweede bod opleveren
 *   2. rij-lock          alles daarna gebeurt onder één lock op `auctions`
 *   3. poortwachters     elk faalgeval met een eigen reden
 *   4. proxy             in één berekening, niet stap voor stap
 *   5. schrijven         bod, stand, tellers en events binnen dezelfde lock
 *   6. uitzenden         pas ná de commit
 */
final class PlaceBid
{
    public function __construct(
        private readonly ProxyResolver $resolver,
        private readonly BidGuards $guards,
        private readonly BidWriter $writer,
    ) {}

    public function handle(
        Auction $auction,
        User $user,
        int $amountCents,
        string $idempotencyKey,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Bid {
        /*
         | Idempotentie vóór de lock. Een dubbele submit of een retry na een
         | timeout geeft hetzelfde bod terug in plaats van een tweede bod.
         | Binnen de transactie wordt het nog eens gecontroleerd - dáár is het
         | pas waterdicht; hier scheelt het alleen een lock.
         */
        if ($existing = $this->replay($auction, $user, $idempotencyKey)) {
            return $existing;
        }

        $announcement = null;

        $bid = DB::transaction(
            function () use ($auction, $user, $amountCents, $idempotencyKey, $ip, $userAgent, &$announcement): Bid {
                /*
                 | De lock zit op `auctions`, niet op `bids`. Twee nieuwe rijen
                 | in bids blokkeren elkaar niet; die lock zou niets oplossen
                 | (hfst. 6).
                 */
                $locked = Auction::query()
                    ->whereKey($auction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Twee gelijktijdige verzoeken met dezelfde sleutel serialiseren
                // op de lock hierboven; de tweede ziet de rij van de eerste.
                if ($existing = $this->replay($locked, $user, $idempotencyKey)) {
                    return $existing;
                }

                // De kavel verandert niet tijdens een bod en hoeft dus niet
                // meegelockt te worden.
                $locked->setRelation('lot', $locked->lot()->sole());

                // Bieden hoort bij een gewone veiling. Op een afslag mijn je af.
                $this->guards->ensureSaleType($locked, SaleType::Auction);
                $this->guards->ensureOpen($locked);
                $this->guards->ensureNotSeller($locked, $user);
                $this->guards->ensureVerified($user, $amountCents);
                $this->guards->ensureDepositCovers($locked, $user, $amountCents);
                // Vóór de bedragcontrole: "je bent al de hoogste bieder" is een
                // nuttiger antwoord dan "je bod is te laag".
                $this->guards->ensureNotAlreadyLeading($locked, $user);
                $this->guards->ensureMeetsMinimum($locked, $amountCents);

                $outcome = $this->resolver->resolve(
                    auction: $locked,
                    challengerId: $user->id,
                    challengerAmountCents: $amountCents,
                    amountIsMaximum: false,
                );

                $result = $this->writer->commit($locked, $outcome, $idempotencyKey, $ip, $userAgent);

                // Bewijs in het auditlog, in dezelfde transactie als het bod.
                Audit::record('bid.placed', $result->challengerBid(), $user, after: [
                    'auction_id' => $locked->id,
                    'amount_cents' => $amountCents,
                    'current_price_cents' => $locked->refresh()->current_price_cents,
                ], ip: $ip);

                $announcement = fn () => $this->writer->announce($locked, $outcome, $result);

                // De rij van de uitdager zelf: die vertelt of hij leidt of
                // direct is overboden door een automatisch maximum.
                return $result->challengerBid();
            },
            attempts: 3,
        );

        if ($announcement !== null) {
            DB::afterCommit($announcement);
        }

        return $bid;
    }

    /**
     * Een sleutel geldt per gebruiker en per veiling: een hergebruikte sleutel
     * van iemand anders of van een andere veiling geeft nooit een bod terug.
     */
    private function replay(Auction $auction, User $user, string $idempotencyKey): ?Bid
    {
        $bid = Bid::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($bid === null) {
            return null;
        }

        // Dezelfde sleutel van iemand anders of van een andere veiling: nooit
        // andermans bod teruggeven, en ook niet stil een nieuw bod plaatsen.
        if ($bid->user_id !== $user->id || $bid->auction_id !== $auction->id) {
            throw ValidationException::withMessages(['idempotency_key' => __('auctions.errors.key_in_use')]);
        }

        return $bid;
    }
}
