<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Models\IvyConversation;
use App\Models\Lot;
use App\Support\Money\Money;
use App\Support\PlatformFacts;
use Illuminate\Support\Str;

/**
 * Het systeembericht per beurt: de basisprompt onder versiebeheer in
 * resources/prompts/ivy, de rol, de platformfeiten uit de instellingen en
 * de pagina (met een kavel als die er is). Kavelteksten van anderen gaan
 * tussen <untrusted>-markers.
 */
final class ContextBuilder
{
    public const string VERSION = 'v1';

    public function build(IvyConversation $conversation, ?Lot $lot): string
    {
        $template = (string) file_get_contents(resource_path('prompts/ivy/base.md'));

        return strtr($template, [
            '{role}' => __('ivy.roles.'.$conversation->role),
            '{facts}' => $this->facts(),
            '{context}' => $this->context($conversation, $lot),
        ]);
    }

    public function facts(): string
    {
        $facts = PlatformFacts::all();
        $tiers = collect($facts['fee_tiers'])->map(fn (array $tier) => ($tier['up_to_cents'] === null ? 'daarboven' : 'tot '.Money::format((int) $tier['up_to_cents'])).': '.$tier['percent'].'%')->implode(', ');
        $deposits = collect($facts['deposit_tiers'])->map(fn (array $tier) => 'boven '.Money::format((int) $tier['above_cents']).' borg '.Money::format((int) $tier['deposit_cents']))->implode(', ');

        return implode(' ', [
            'Succesfee voor verkopers (staffel op omzet van 12 maanden): '.$tiers.'.',
            'Plaatsen is gratis; alleen betalen bij verkoop. Kopers betalen geen opgeld.',
            'Administratiekosten voor de koper: '.Money::format((int) $facts['admin_fee_cents']).' excl. btw per bestelling.',
            'Bieden tot '.Money::format((int) $facts['bid_free_limit_cents']).' met bevestigde mail en telefoon; daarboven identiteit; '.$deposits.'.',
            'Betaaltermijn '.$facts['payment_deadline_hours'].' uur; meldtermijn '.$facts['dispute_window_hours'].' uur; automatisch geleverd na '.$facts['auto_deliver_after_days'].' dagen; verkoper reageert binnen '.$facts['seller_response_hours'].' uur; uitbetaling na de houdtermijn van '.$facts['payout_hold_hours'].' uur.',
        ]);
    }

    private function context(IvyConversation $conversation, ?Lot $lot): string
    {
        $parts = ['Pagina: '.($conversation->page_type ?? 'onbekend').'.'];

        if ($lot !== null) {
            $lot->loadMissing(['auction', 'category']);
            $parts[] = sprintf('[kavel_id:%d] Kavel %s "%s" (%s), status %s, %s.', $lot->id, $lot->public_id, $lot->title, $lot->category?->name ?? 'categorie onbekend', $lot->status->label(), $lot->auction ? 'huidig bod '.Money::format($lot->currentPriceCents()).($lot->auction->reserve_price_cents !== null ? ', reserve '.($lot->auction->current_price_cents >= $lot->auction->reserve_price_cents ? 'gehaald' : 'nog niet gehaald') : ', geen reserve') : 'geen veiling');
            $parts[] = '<untrusted>'.Str::limit((string) $lot->description, 800).'</untrusted>';
        }

        return implode(' ', $parts);
    }
}
