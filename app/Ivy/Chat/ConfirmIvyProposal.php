<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Auctions\Actions\AcceptDutchPrice;
use App\Auctions\Actions\BuyNow;
use App\Auctions\Actions\PlaceAutoBid;
use App\Auctions\Actions\PlaceBid;
use App\Auctions\Exceptions\BidRejected;
use App\Auctions\Exceptions\BidRejectionReason;
use App\Auctions\Support\BidGuards;
use App\Lots\PublishLot;
use App\Models\IvyActionProposal;
use App\Models\IvyLotMode;
use App\Models\User;
use App\Support\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * De bevestiging: vergelijk de verwachte stand met de echte, roep daarna de
 * bestaande actie aan (PlaceBid binnen de bestaande lock). Een dubbele tik
 * geeft via de idempotency key dezelfde uitslag. Elke uitvoering komt in
 * het auditlog.
 */
final class ConfirmIvyProposal
{
    public function __construct(
        private readonly Proposals $proposals,
        private readonly BidGuards $guards,
        private readonly LotModes $modes,
    ) {}

    /**
     * @return array{state: string, message_key: string, current_cents: int|null, own_bid_still_valid: bool|null, result: array<string, mixed>|null}
     */
    public function handle(IvyActionProposal $proposal, User $user, ?int $expectedCurrentCents, ?string $expectedEndsAt, string $idempotencyKey, ?string $ip = null): array
    {
        abort_unless($proposal->user_id === $user->id, 404);

        // Dubbel tikken: dezelfde sleutel geeft de eerste uitslag terug.
        if ($proposal->idempotency_key !== null && hash_equals($proposal->idempotency_key, $idempotencyKey) && $proposal->status === IvyActionProposal::EXECUTED) {
            return ['state' => IvyActionProposal::EXECUTED, 'message_key' => 'ivy.proposal.executed', 'current_cents' => $proposal->result['current_cents'] ?? null, 'own_bid_still_valid' => null, 'result' => $proposal->result];
        }

        if (! $proposal->isOpen()) {
            return ['state' => $proposal->status, 'message_key' => 'ivy.proposal.'.$proposal->status, 'current_cents' => null, 'own_bid_still_valid' => null, 'result' => null];
        }

        $lot = $proposal->lot()->with('auction')->firstOrFail();
        $auction = $lot->auction;

        if ($auction !== null && ($auction->isClosed() || ! $lot->status->isPublic()) && $proposal->type !== IvyActionProposal::PUBLISH) {
            $proposal->forceFill(['status' => IvyActionProposal::EXPIRED])->save();

            return ['state' => IvyActionProposal::EXPIRED, 'message_key' => 'ivy.proposal.closed', 'current_cents' => $auction->current_price_cents, 'own_bid_still_valid' => false, 'result' => null];
        }

        // Verschoven prijs of eindtijd: 409, opnieuw bevestigen.
        if ($auction !== null && in_array($proposal->type, [IvyActionProposal::BID, IvyActionProposal::DUTCH_ACCEPT, IvyActionProposal::BUY_NOW], true)) {
            $priceMoved = $expectedCurrentCents !== null && $expectedCurrentCents !== $auction->current_price_cents;
            $endMoved = $expectedEndsAt !== null && abs(strtotime($expectedEndsAt) - $auction->ends_at->getTimestamp()) > 1;

            if ($priceMoved || $endMoved) {
                $stillValid = $proposal->type !== IvyActionProposal::BID || $proposal->amount_cents >= $this->guards->minimumBid($auction);
                $proposal->forceFill(['status' => IvyActionProposal::PRICE_CHANGED, 'expected_current_cents' => $auction->current_price_cents, 'expected_ends_at' => $auction->ends_at])->save();

                return ['state' => IvyActionProposal::PRICE_CHANGED, 'message_key' => 'ivy.bid.price_changed', 'current_cents' => $auction->current_price_cents, 'own_bid_still_valid' => $stillValid, 'result' => null];
            }
        }

        $proposal->forceFill(['status' => IvyActionProposal::CONFIRMED, 'confirmed_at' => now(), 'idempotency_key' => $idempotencyKey, 'confirmed_ip' => $ip])->save();

        try {
            $result = DB::transaction(fn () => match ($proposal->type) {
                IvyActionProposal::BID => $this->bid($proposal, $user, $idempotencyKey, $ip),
                IvyActionProposal::AUTO_BID => $this->autoBid($proposal, $user, $ip),
                IvyActionProposal::DUTCH_ACCEPT => $this->dutch($proposal, $user, $idempotencyKey, $ip),
                IvyActionProposal::BUY_NOW => $this->buyNow($proposal, $user, $idempotencyKey, $ip),
                IvyActionProposal::PUBLISH => $this->publish($proposal, $user, $ip),
                default => throw ValidationException::withMessages(['proposal' => 'Dit soort voorstel staat achter een vlag.']),
            });
        } catch (BidRejected $exception) {
            $proposal->forceFill(['status' => IvyActionProposal::REJECTED, 'result' => ['reason' => $exception->reason->value, 'message' => $exception->reason->label()]])->save();

            return ['state' => IvyActionProposal::REJECTED, 'message_key' => 'ivy.bid.rejected', 'current_cents' => $auction?->fresh()?->current_price_cents, 'own_bid_still_valid' => false, 'result' => $proposal->result];
        } catch (ValidationException $exception) {
            $proposal->forceFill(['status' => IvyActionProposal::REJECTED, 'result' => ['message' => collect($exception->errors())->flatten()->first()]])->save();

            return ['state' => IvyActionProposal::REJECTED, 'message_key' => 'ivy.bid.rejected', 'current_cents' => $auction?->fresh()?->current_price_cents, 'own_bid_still_valid' => false, 'result' => $proposal->result];
        }

        $proposal->forceFill(['status' => IvyActionProposal::EXECUTED, 'executed_at' => now(), 'result' => $result])->save();

        Audit::record('ivy.proposal_executed', $lot, $user, after: ['proposal_id' => $proposal->id, 'type' => $proposal->type, 'amount_cents' => $proposal->amount_cents, 'result' => $result], ip: $ip);

        return ['state' => IvyActionProposal::EXECUTED, 'message_key' => 'ivy.proposal.executed', 'current_cents' => $result['current_cents'] ?? null, 'own_bid_still_valid' => null, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    private function bid(IvyActionProposal $proposal, User $user, string $idempotencyKey, ?string $ip): array
    {
        $auction = $proposal->lot->auction;
        $bid = app(PlaceBid::class)->handle($auction, $user, (int) $proposal->amount_cents, 'ivy:'.$proposal->id.':'.$idempotencyKey, $ip);

        return ['bid_id' => $bid->id, 'amount_cents' => $bid->amount_cents, 'status' => $bid->status->value, 'current_cents' => $auction->fresh()->current_price_cents];
    }

    /**
     * @return array<string, mixed>
     */
    private function autoBid(IvyActionProposal $proposal, User $user, ?string $ip): array
    {
        $auction = $proposal->lot->auction;
        $autoBid = app(PlaceAutoBid::class)->handle($auction, $user, (int) $proposal->amount_cents);
        $this->modes->set($user, $proposal->lot, IvyLotMode::AUTO, (int) $proposal->amount_cents, $ip);

        return ['auto_bid_id' => $autoBid->id, 'limit_cents' => (int) $proposal->amount_cents, 'current_cents' => $auction->fresh()->current_price_cents];
    }

    /**
     * @return array<string, mixed>
     */
    private function dutch(IvyActionProposal $proposal, User $user, string $idempotencyKey, ?string $ip): array
    {
        if (! IvyFlags::on('auctions.dutch')) {
            throw BidRejected::because(BidRejectionReason::SaleTypeMismatch);
        }

        $auction = $proposal->lot->auction;
        $order = app(AcceptDutchPrice::class)->handle($auction, $user, (int) $proposal->amount_cents, 'ivy:'.$proposal->id.':'.$idempotencyKey, $ip);

        return ['order_id' => $order->id, 'order_number' => $order->order_number, 'current_cents' => (int) $proposal->amount_cents];
    }

    /**
     * @return array<string, mixed>
     */
    private function buyNow(IvyActionProposal $proposal, User $user, string $idempotencyKey, ?string $ip): array
    {
        $auction = $proposal->lot->auction;
        $order = app(BuyNow::class)->handle($auction, $user, 'ivy:'.$proposal->id.':'.$idempotencyKey, $ip);

        return ['order_id' => $order->id, 'order_number' => $order->order_number, 'current_cents' => $order->hammer_cents];
    }

    /**
     * @return array<string, mixed>
     */
    private function publish(IvyActionProposal $proposal, User $user, ?string $ip): array
    {
        $lot = app(PublishLot::class)->handle($proposal->lot, $user, $ip);

        return ['lot_id' => $lot->id, 'status' => $lot->status->value];
    }
}
