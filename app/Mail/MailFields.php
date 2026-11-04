<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lot;
use App\Models\Order;
use App\Models\User;
use App\Support\Money\Money;
use App\Support\PlatformFacts;

/**
 * De velden in een mail ({{voornaam}}, {{kavel.titel}}, {{kavel.bod}},
 * {{kavel.sluit}}) en de voorbeelddata voor de preview. Kavels en bod
 * worden bij het versturen opgehaald, niet bij het opslaan.
 */
final class MailFields
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function build(?User $user, array $context = []): array
    {
        $lot = $context['lot'] ?? null;
        $order = $context['order'] ?? null;
        $facts = PlatformFacts::all();

        $fields = [
            'voornaam' => $user ? (explode(' ', trim((string) $user->name))[0] ?: $user->name) : '',
            'naam' => $user?->name ?? '',
            'biednaam' => $user?->publicName() ?? '',
            'link' => $context['link'] ?? route('home'),
            'links' => [
                'veilingen' => route('lots.index'),
                'verkopen' => route('sell'),
                'alerts' => route('dashboard.alerts'),
                'volglijst' => route('dashboard.watchlist'),
                'biedingen' => route('dashboard.bids'),
                'uitbetalingen' => route('dashboard.payouts'),
                'financien' => route('dashboard.finance'),
                'borg' => route('verification.overview'),
                'contact' => route('contact'),
            ],
            'feiten' => [
                'biedgrens' => Money::format(PlatformFacts::bidFreeLimitCents()),
                'borggrens' => Money::format((int) ($facts['deposit_tiers'][0]['above_cents'] ?? 250_000)),
                'borg' => Money::format((int) ($facts['deposit_tiers'][0]['deposit_cents'] ?? 20_000)),
                'borggrens_hoog' => Money::format((int) ($facts['deposit_tiers'][1]['above_cents'] ?? 1_000_000)),
                'borg_hoog' => Money::format((int) ($facts['deposit_tiers'][1]['deposit_cents'] ?? 50_000)),
                'betaaltermijn' => self::hours((int) $facts['payment_deadline_hours']),
                'meldtermijn' => self::hours((int) $facts['dispute_window_hours']),
                'vrijgave' => (int) $facts['auto_deliver_after_days'].' dagen',
                'reactietermijn' => self::hours((int) $facts['seller_response_hours']),
                'administratie' => Money::format(PlatformFacts::adminFeeCents()),
            ],
        ];

        if ($lot instanceof Lot) {
            $lot->loadMissing(['auction', 'media']);
            $fields['kavel'] = [
                'titel' => $lot->title,
                'nummer' => $lot->public_id,
                'bod' => $lot->auction ? Money::format($lot->currentPriceCents()) : '',
                'sluit' => $lot->auction?->ends_at?->timezone(config('aucvy.display_timezone'))->isoFormat('dddd D MMMM, HH:mm') ?? '',
                'link' => route('lots.show', $lot),
                'bewerken' => route('dashboard.lots.edit', $lot),
                'dossier' => route('dashboard.lots.show', $lot),
                'verspreiding' => route('dashboard.lots.show', [$lot, 'tab' => 'verspreiding']),
                'foto' => $lot->media->first()?->path,
            ];
        }

        if ($order instanceof Order) {
            $fields['bestelling'] = [
                'nummer' => $order->order_number,
                'totaal' => Money::format($order->total_cents),
                'hamer' => Money::format($order->hammer_cents),
                'opgeld' => Money::format($order->buyer_premium_cents),
                'administratie' => Money::format((int) ($order->getAttributes()['admin_fee_cents'] ?? 0)),
                'transport' => Money::format($order->transport_cents),
                'link' => route('orders.show', $order),
            ];
            $fields['deadline'] = $order->payment_deadline_at?->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') ?? '';
        }

        foreach ($context as $key => $value) {
            if (! in_array($key, ['lot', 'order', 'lots'], true)) {
                $fields[$key] = $value;
            }
        }

        return $fields;
    }

    /**
     * Voorbeelddata voor de preview in de builder.
     *
     * @return array<string, mixed>
     */
    public static function sample(): array
    {
        $fields = self::build(null);

        return array_replace_recursive($fields, [
            'voornaam' => 'Karin', 'naam' => 'Karin de Vries', 'biednaam' => 'Bieder 4821',
            'kavel' => ['titel' => 'Herman Miller Aeron, 24 stuks', 'nummer' => '#1042', 'bod' => '€ 3.150,00', 'sluit' => 'woensdag 4 november, 20:00', 'link' => route('lots.index'), 'bewerken' => route('dashboard.lots'), 'dossier' => route('dashboard.lots'), 'verspreiding' => route('dashboard.lots'), 'foto' => null],
            'bestelling' => ['nummer' => '#2026-00156', 'totaal' => '€ 3.905,00', 'hamer' => '€ 3.550,00', 'opgeld' => '€ 0,00', 'administratie' => '€ 15,00', 'transport' => '€ 340,00', 'link' => route('orders.index')],
            'bedrag' => '€ 200,00', 'limiet' => '€ 4.000,00', 'moment' => 'donderdag 5 november, 10:00', 'deadline' => '4 nov, 20:00', 'resterend' => '34 minuten',
            'eigen_bod' => '€ 2.900,00', 'advies' => 'Met € 3.400 ben je weer de hoogste, binnen je budget. Vergelijkbare partijen gingen voor € 3.500 tot € 4.100. Indicatie op basis van 14 kavels.',
            'zoekvraag' => '20 ergonomische stoelen, max € 4.000', 'waarom' => 'Precies 20 stuks, binnen je budget en 24 km van je adres.', 'aantal' => '3', 'volgers' => '9',
            'code' => '482 917', 'adres' => 'Industrieweg 12, Utrecht', 'zaak' => '#G-2026-0042', 'omschrijving' => 'Planetaire mixer: motor start niet.', 'reden' => 'De foto was niet scherp.',
            'taken' => 'Transport of ophalen. De factuur toevoegen. Mijn advies over verkoopvorm en prijs goedkeuren.', 'views' => '312', 'bieders' => '2', 'tip' => 'Drie kopers vroegen naar de maatvoering. Stuur je me die, dan zet ik het in de tekst.',
            'vraag' => 'Zit de afzuigkap er ook bij?', 'concept' => 'Nee, de afzuigkap hoort er niet bij.', 'reserve' => '€ 9.000,00', 'netto' => '€ 7.565,00',
            'fee' => ['percentage' => '15%', 'bedrag' => '€ 1.335,00'], 'factuur' => ['nummer' => 'F-2026-00412', 'btw' => '€ 280,35', 'link' => route('dashboard.finance')],
            'staffel' => ['grens' => '€ 25.000', 'vorige' => '15%', 'volgende' => '€ 50.000'], 'categorie' => 'horeca en kantoor', 'titel' => 'Je restaurant stoppen: zo verkoop je je inventaris', 'intro' => 'Wat bepaalt de opbrengst, wanneer begin je, en wat moet je regelen voor de laatste dag?',
        ]);
    }

    private static function hours(int $hours): string
    {
        return $hours % 24 === 0 && $hours >= 48 ? intdiv($hours, 24).' dagen' : $hours.' uur';
    }

    /**
     * Vervangt {{veld}} en {{groep.veld}}; onbekend wordt leeg.
     *
     * @param  array<string, mixed>  $fields
     */
    public static function resolve(string $text, array $fields): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', function (array $match) use ($fields): string {
            $value = data_get($fields, $match[1]);

            return is_scalar($value) ? (string) $value : '';
        }, $text);
    }
}
