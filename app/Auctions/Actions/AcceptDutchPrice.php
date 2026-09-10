<?php

declare(strict_types=1);

namespace App\Auctions\Actions;

use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Support\BidGuards;
use App\Auctions\Support\BidWriter;
use App\Auctions\Support\DutchPriceCalculator;
use App\Enums\BidType;
use App\Enums\SaleType;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Afmijnen: de dalende prijs accepteren.
 *
 * Eerste klik wint. Dat wordt afgedwongen door dezelfde rij-lock als bij een
 * gewoon bod, plus het simpele feit dat een veiling één `winner_id` heeft - er
 * is structureel geen plek voor een tweede winnaar (hfst. 6).
 *
 * De koper stuurt mee wélke prijs hij op zijn scherm zag. De server rekent de
 * prijs opnieuw uit en dát bedrag geldt. Zat het scherm er meer dan één stap
 * naast, dan wordt er niets afgerekend maar de actuele prijs teruggegeven -
 * anders koopt iemand voor een bedrag dat hij nooit heeft gezien.
 */
final class AcceptDutchPrice
{
    public function __construct(
        private readonly DutchPriceCalculator $calculator,
        private readonly BidGuards $guards,
        private readonly BidWriter $writer,
        private readonly CloseAuction $closeAuction,
    ) {}

    public function handle(
        Auction $auction,
        User $user,
        int $seenPriceCents,
        string $idempotencyKey,
        ?string $ip = null,
        ?string $userAgent = null,
    ): Order {
        // Een retry na een timeout mag niet nóg een keer afrekenen. Expliciet
        // opvragen in plaats van via de relatie: lazy loading staat uit.
        if ($existing = Bid::firstWhere('idempotency_key', $idempotencyKey)) {
            return Order::where('auction_id', $existing->auction_id)->sole();
        }

        return DB::transaction(function () use (
            $auction, $user, $seenPriceCents, $idempotencyKey, $ip, $userAgent
        ): Order {
            $locked = Auction::query()
                ->whereKey($auction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->setRelation('lot', $locked->lot()->sole());

            $this->guards->ensureSaleType($locked, SaleType::Dutch);
            // Een gesloten veiling is al afgemijnd door iemand anders: de
            // tweede klik landt hier en krijgt een nette melding.
            $this->guards->ensureOpen($locked);
            $this->guards->ensureNotSeller($locked, $user);
            $this->guards->ensureVerified($user);

            $price = $this->calculator->priceAt($locked);

            if (! $this->calculator->isWithinTolerance($locked, $seenPriceCents)) {
                throw BidRejected::priceMoved($price);
            }

            // Over de prijs die daadwerkelijk afgerekend wordt, niet over wat
            // de client dacht te zien.
            $this->guards->ensureDepositCovers($locked, $user, $price);

            $this->writer->commitImmediateSale(
                auction: $locked,
                user: $user,
                priceCents: $price,
                type: BidType::Dutch,
                idempotencyKey: $idempotencyKey,
                ip: $ip,
                userAgent: $userAgent,
            );

            /*
             | Een afslag eindigt bij de eerste acceptatie. Sluiten gebeurt via
             | dezelfde Action als een gewone veiling, zodat de order, het
             | sluitevent en de kavelstatus op één plek geregeld blijven.
             */
            $order = $this->closeAuction->handle($locked);

            // CloseAuction levert null als er geen winnaar is. Dat kan hier
            // niet: we hebben net een winnend bod geschreven.
            return $order ?? throw new LogicException(
                "Afmijnen op veiling {$locked->id} leverde geen order op."
            );
        }, attempts: 3);
    }
}
