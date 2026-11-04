<?php

declare(strict_types=1);

namespace App\Messaging\Rules;

use App\Enums\OrderStatus;
use App\Models\Conversation;
use App\Models\MessageModeration;
use App\Reviews\ReviewModerator;
use Illuminate\Support\Str;

/**
 * De vaste regels van de moderator-bot (briefing nieuwe functies, tabel
 * botsignalen). Ze lopen synchroon, vóór het opslaan, en het model kan ze
 * niet omzeilen. De bot blokkeert alleen in duidelijke gevallen en legt
 * elke keer uit waarom; sancties neemt altijd een medewerker.
 *
 *  - Contact vóór betaling (telefoon, mail, "app me"): maskeren.
 *  - Buiten het platform om (contant, IBAN, Tikkie): tegenhouden.
 *  - Biedmanipulatie: tegenhouden, signaal voor Risk.
 *  - Grof of discriminerend: zelfde filter als bij reviews; weigeren.
 *  - Phishing (verkorte link, inlogpagina): weigeren.
 *  - Andere afspraak dan de order: laten staan, een knop voorstellen.
 */
final class MessageRules
{
    private const PHONE = '/(?<![\d])(?:\+?31|0031|0)\s?[\.\-]?\s?[1-9](?:[\s\.\-]?\d){8}(?![\d])/u';

    private const EMAIL = '/[A-Za-z0-9._%+\-]+\s?(?:@|\(at\)|\[at\])\s?[A-Za-z0-9.\-]+\s?(?:\.|\(dot\)|\[dot\])\s?[A-Za-z]{2,}/u';

    private const IBAN = '/\b[A-Z]{2}\s?\d{2}(?:\s?[A-Z0-9]){10,30}\b/u';

    private const URL = '/\bhttps?:\/\/[^\s]+|\bwww\.[^\s]+|\b[a-z0-9\-]+\.(?:ly|co|gl|gd|cc|at|com|nl|be|de|io)\/[^\s]+/iu';

    public function __construct(private readonly ReviewModerator $reviewFilter) {}

    public function evaluate(string $body, Conversation $conversation): RuleOutcome
    {
        $text = Str::lower($body);
        $normalised = $this->normalise($text);

        // 1. Grof of discriminerend: dezelfde lijsten als bij reviews.
        if (($reason = $this->reviewFilter->reasonToHold($body)) !== null && $reason->value !== 'personal_data') {
            return new RuleOutcome(MessageModeration::REJECT, 'offensive', 0.9, explanation: __('messaging.explanations.offensive'));
        }

        // 2. Phishing: verkorte links of een inlogpagina.
        if ($this->mentionsShortLink($text) || ($this->containsAny($normalised, config('messaging.phishing', [])) && preg_match(self::URL, $text) === 1)) {
            return new RuleOutcome(MessageModeration::REJECT, 'phishing', 0.9, explanation: __('messaging.explanations.phishing'));
        }

        // 3. Buiten het platform om: IBAN of het woord zelf.
        if (preg_match(self::IBAN, $body) === 1 || $this->containsAny($normalised, config('messaging.off_platform', []))) {
            return new RuleOutcome(MessageModeration::HOLD, 'off_platform', preg_match(self::IBAN, $body) === 1 ? 0.95 : 0.75, explanation: __('messaging.explanations.off_platform'));
        }

        // 4. Biedmanipulatie.
        if ($this->containsAny($normalised, config('messaging.bid_manipulation', []))) {
            return new RuleOutcome(MessageModeration::HOLD, 'bid_manipulation', 0.7, explanation: __('messaging.explanations.bid_manipulation'));
        }

        // 5. Contact vóór betaling: maskeren, met uitleg aan de afzender.
        if ($this->contactIsBlocked($conversation)) {
            $masked = $this->maskContact($body);

            if ($masked !== $body) {
                return new RuleOutcome(MessageModeration::MASK, 'contact_before_payment', 0.9, maskedBody: $masked, explanation: __('messaging.explanations.contact'));
            }
        }

        // 6. Een andere afspraak dan de order: laten staan, knop voorstellen.
        if ($this->containsAny($normalised, config('messaging.different_deal', []))) {
            $hint = $this->mentionsMoney($normalised) ? 'deal_money' : 'deal_pickup';

            return new RuleOutcome(MessageModeration::FLAG, 'different_deal', 0.6, hint: $hint, explanation: __('messaging.explanations.'.$hint));
        }

        return RuleOutcome::allow();
    }

    /**
     * Telefoonnummers en mailadressen afschermen, ook met spaties en punten
     * ertussen. Het resultaat is leesbaar maar niet bruikbaar.
     */
    public function maskContact(string $body): string
    {
        $masked = (string) preg_replace(self::EMAIL, '••• afgeschermd •••', $body);
        $masked = (string) preg_replace(self::PHONE, '••• afgeschermd •••', $masked);

        foreach (['app me', 'whatsapp me', 'bel me', 'mail me', 'stuur je nummer', 'je 06'] as $phrase) {
            $masked = (string) preg_replace('/\b'.preg_quote($phrase, '/').'\b/iu', '••• afgeschermd •••', $masked);
        }

        return $masked;
    }

    private function contactIsBlocked(Conversation $conversation): bool
    {
        if (! config('messaging.mask_contact_before_payment')) {
            return false;
        }

        $order = $conversation->order;

        return $order === null || in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::Cancelled], true);
    }

    private function mentionsShortLink(string $text): bool
    {
        foreach ((array) config('messaging.short_link_hosts', []) as $host) {
            if (str_contains($text, $host.'/')) {
                return true;
            }
        }

        return false;
    }

    private function mentionsMoney(string $text): bool
    {
        return preg_match('/€|euro|eur\b|prijs|korting|eraf|garantie/u', $text) === 1;
    }

    /**
     * @param  list<string>  $phrases
     */
    private function containsAny(string $haystack, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if ($phrase !== '' && str_contains($haystack, $this->normalise(Str::lower($phrase)))) {
                return true;
            }
        }

        return false;
    }

    private function normalise(string $text): string
    {
        $text = Str::ascii($text);

        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/[^a-z0-9€]+/', ' ', $text)));
    }
}
