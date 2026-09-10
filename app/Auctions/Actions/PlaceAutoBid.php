<?php

declare(strict_types=1);

namespace App\Auctions\Actions;

use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Support\BidGuards;
use App\Auctions\Support\BidWriter;
use App\Auctions\Support\ProxyResolver;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\AutoBid;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Automatisch meestappen tot…" - een maximum, geen bod.
 *
 * Het verschil met PlaceBid is de betekenis van het bedrag. Hier zeg je hoe
 * ver je wilt gaan; je betaalt alleen wat nodig was om de nummer twee net voor
 * te blijven. Dat maximum is nooit zichtbaar - niet voor andere bieders, niet
 * voor de verkoper, en niet op een broadcastkanaal. Een maximumbod dat lekt,
 * is een gebruiker die geld verliest (hfst. 7).
 *
 * Verhogen mag, verlagen niet: dat zou een nieuw maximum ónder een bestaand
 * bod zijn en dat kan niet (hfst. 5).
 */
final class PlaceAutoBid
{
    public function __construct(
        private readonly ProxyResolver $resolver,
        private readonly BidGuards $guards,
        private readonly BidWriter $writer,
    ) {}

    public function handle(Auction $auction, User $user, int $maxAmountCents): AutoBid
    {
        $announcement = null;

        $autoBid = DB::transaction(
            function () use ($auction, $user, $maxAmountCents, &$announcement): AutoBid {
                $locked = Auction::query()
                    ->whereKey($auction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->setRelation('lot', $locked->lot()->sole());

                $this->guards->ensureSaleType($locked, SaleType::Auction);
                $this->guards->ensureOpen($locked);
                $this->guards->ensureNotSeller($locked, $user);
                $this->guards->ensureVerified($user);
                // Over het maximum, niet over de huidige prijs: je verplicht je
                // tot dit bedrag, dus daar hoort de borg bij te passen.
                $this->guards->ensureDepositCovers($locked, $user, $maxAmountCents);

                $existing = AutoBid::query()
                    ->where('auction_id', $locked->id)
                    ->where('user_id', $user->id)
                    ->where('active', true)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null && $maxAmountCents <= $existing->max_amount_cents) {
                    throw BidRejected::autoBidNotIncreasing($existing->max_amount_cents + 1);
                }

                $isLeading = $this->guards->isLeading($locked, $user);

                /*
                 | Wie al leidt, hoeft geen minimum te halen: hij verhoogt
                 | alleen stilletjes zijn plafond. Wie níét leidt, moet er wel
                 | daadwerkelijk mee kunnen bieden.
                 */
                if (! $isLeading) {
                    $this->guards->ensureMeetsMinimum($locked, $maxAmountCents);
                }

                $autoBid = $this->store($locked, $user, $maxAmountCents, $existing);

                /*
                 | Leid je al, dan verandert de prijs niet. Je maximum omhoog
                 | zetten mag nooit je eigen prijs opdrijven - dat is precies
                 | waarom een maximum verborgen blijft.
                 */
                if ($isLeading) {
                    return $autoBid;
                }

                $outcome = $this->resolver->resolve(
                    auction: $locked,
                    challengerId: $user->id,
                    challengerAmountCents: $maxAmountCents,
                    amountIsMaximum: true,
                );

                $result = $this->writer->commit(
                    auction: $locked,
                    outcome: $outcome,
                    // Automatisch bieden komt niet van een formulier met een
                    // Idempotency-Key; de unieke index op auto_bids doet hier
                    // het werk dat die sleutel elders doet.
                    idempotencyKey: 'auto:'.$locked->id.':'.$user->id.':'.Str::random(16),
                );

                $announcement = fn () => $this->writer->announce($locked, $outcome, $result);

                return $autoBid;
            },
            attempts: 3,
        );

        if ($announcement !== null) {
            DB::afterCommit($announcement);
        }

        return $autoBid;
    }

    private function store(Auction $auction, User $user, int $maxAmountCents, ?AutoBid $existing): AutoBid
    {
        if ($existing !== null) {
            $existing->max_amount_cents = $maxAmountCents;
            $existing->save();

            return $existing;
        }

        return AutoBid::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'max_amount_cents' => $maxAmountCents,
            'active' => true,
        ]);
    }
}
