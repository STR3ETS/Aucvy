<?php

declare(strict_types=1);

namespace App\Ivy\Chat;

use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * De pagina's waar Ivy naartoe mag verwijzen of de klant heen mag sturen:
 * een vaste lijst per rol, nooit een adres dat het model zelf bedenkt.
 * Dezelfde lijst staat in het systeembericht en achter de tool open_page.
 */
final class Pages
{
    /** @var array<string, array{0: string, 1: string, 2: string}> sleutel => [label, routenaam, voor wie: all|user|seller|finance] */
    private const PAGES = [
        'veilingen' => ['Alle veilingen', 'lots.index', 'all'],
        'kavel_plaatsen' => ['Kavel plaatsen', 'sell', 'all'],
        'veelgestelde_vragen' => ['Veelgestelde vragen', 'faq', 'all'],
        'contact' => ['Contact met het team', 'contact', 'all'],
        'inloggen' => ['Inloggen', 'login', 'guest'],
        'registreren' => ['Account aanmaken', 'register', 'guest'],
        'dashboard' => ['Dashboard', 'dashboard', 'user'],
        'biedingen' => ['Mijn biedingen', 'dashboard.bids', 'user'],
        'bestellingen' => ['Bestellingen', 'orders.index', 'user'],
        'berichten' => ['Berichten', 'messages.index', 'user'],
        'volglijst' => ['Volglijst', 'dashboard.watchlist', 'user'],
        'alerts' => ['Alerts', 'dashboard.alerts', 'user'],
        'meldingen' => ['Meldingen', 'notifications.index', 'user'],
        'geschillen' => ['Geschillen', 'dashboard.disputes', 'user'],
        'instellingen' => ['Instellingen', 'account.settings', 'user'],
        'verificatie' => ['Verificatie', 'verification.overview', 'user'],
        'mijn_kavels' => ['Mijn kavels', 'dashboard.lots', 'seller'],
        'mijn_veilingen' => ['Mijn veilingen', 'dashboard.sales', 'seller'],
        'uitbetalingen' => ['Uitbetalingen', 'dashboard.payouts', 'finance'],
        'financien' => ['Financiën', 'dashboard.finance', 'finance'],
    ];

    /**
     * @return array<string, array{label: string, url: string}>
     */
    public function for(?User $user): array
    {
        $pages = [];

        foreach (self::PAGES as $key => [$label, $route, $audience]) {
            $allowed = match ($audience) {
                'all' => true,
                'guest' => $user === null,
                'user' => $user !== null,
                'seller' => $user?->company_id !== null,
                'finance' => $user?->company_id !== null && $user->canSeeCompanyFinance(),
                default => false,
            };

            if ($allowed && Route::has($route)) {
                $pages[$key] = ['label' => $label, 'url' => route($route, [], false)];
            }
        }

        return $pages;
    }

    /**
     * De regel voor het systeembericht: sleutel, naam en pad.
     */
    public function describe(?User $user): string
    {
        return collect($this->for($user))
            ->map(fn (array $page, string $key): string => $key.' = '.$page['label'].' ('.$page['url'].')')
            ->implode('; ');
    }

    /**
     * De kavel waar een pad over gaat: de openbare kavelpagina of het
     * dossier in het dashboard. Zo weet Ivy na een paginawissel zonder
     * herladen nog steeds waar de klant naar kijkt.
     */
    public function lotFromPath(string $path): ?Lot
    {
        if (preg_match('#^/kavel/([a-z0-9-]+)#i', $path, $match) === 1) {
            return Lot::query()->where('slug', $match[1])->first();
        }

        if (preg_match('#^/dashboard/kavels/(\d+)#', $path, $match) === 1) {
            return Lot::query()->find((int) $match[1]);
        }

        return null;
    }

    /**
     * Alleen een pad binnen de eigen site, zonder host en zonder trucs.
     */
    public static function cleanPath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }

        return Str::limit($path, 300, '');
    }
}
