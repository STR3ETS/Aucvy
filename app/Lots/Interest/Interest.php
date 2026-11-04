<?php

declare(strict_types=1);

namespace App\Lots\Interest;

use App\Models\Bid;
use App\Models\Lot;
use App\Models\LotSignal;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Interesse per kavel (hoofdbriefing p. 7). Wat de koper ziet: Bekeken,
 * Opgeslagen, Gedeeld, Bieders en Kijken nu. Wat de verkoper ziet: ook
 * weergaven, documenten en herkomst. Nooit een label "Rustig"; alleen Hot
 * als de regel daar echt aan voldoet.
 */
final class Interest
{
    /** @var array<string, int> gewicht per signaal in de Hot-score */
    public const WEIGHTS = [LotSignal::VIEWER => 1, LotSignal::DOCUMENT => 2, LotSignal::SAVED => 3, LotSignal::SHARED => 3, 'bidder' => 5];

    public const SOURCES = ['alert', 'google', 'instagram', 'facebook', 'linkedin', 'shared', 'direct', 'other'];

    /**
     * Legt een signaal vast. Verkoper, team en bots tellen niet mee; een
     * dubbel signaal van dezelfde bezoeker op dezelfde dag wordt genegeerd.
     */
    public static function record(Lot $lot, string $kind, string $visitorHash, ?User $user = null, ?string $source = null): void
    {
        if (! in_array($kind, LotSignal::KINDS, true) || $visitorHash === '') {
            return;
        }

        if ($user !== null && ($user->is_staff || $lot->isManagedBy($user))) {
            return;
        }

        $bucket = $kind === LotSignal::VIEW ? now()->format('Y-m-d-H-i-s-').Str::random(4) : now()->format('Y-m-d');

        try {
            LotSignal::create([
                'lot_id' => $lot->id,
                'kind' => $kind,
                'visitor_hash' => $visitorHash,
                'user_id' => $user?->id,
                'source' => $source,
                'bucket' => $bucket,
                'occurred_at' => now(),
            ]);
        } catch (QueryException) {
            // Al geteld voor vandaag.
        }
    }

    /**
     * Een bezoeker herkennen zonder persoonsgegevens op te slaan: de sessie,
     * anders adres en browser, gehasht.
     */
    public static function visitorHash(Request $request): string
    {
        if ($request->user() !== null) {
            return 'u'.$request->user()->id;
        }

        $seed = (string) $request->ip().'|'.(string) $request->userAgent();

        return substr(hash('sha256', $seed), 0, 32);
    }

    /**
     * Herkomst van een bezoek: zoekalert, Google, een kanaal, een gedeelde
     * link of direct.
     */
    public static function sourceOf(Request $request): string
    {
        $utm = Str::lower((string) $request->query('utm_source', ''));
        $medium = Str::lower((string) $request->query('utm_medium', ''));

        if ($medium === 'alert' || $utm === 'alert') {
            return 'alert';
        }

        if ($utm === 'deel' || $request->route()?->getName() === 'lots.shared') {
            return 'shared';
        }

        if (in_array($utm, ['instagram', 'facebook', 'linkedin', 'story'], true)) {
            return $utm === 'story' ? 'instagram' : $utm;
        }

        $referer = Str::lower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));

        return match (true) {
            $referer === '' => 'direct',
            str_contains($referer, 'google.') => 'google',
            str_contains($referer, 'instagram.') => 'instagram',
            str_contains($referer, 'facebook.') => 'facebook',
            str_contains($referer, 'linkedin.') => 'linkedin',
            str_contains($referer, (string) parse_url((string) config('app.url'), PHP_URL_HOST)) => 'direct',
            default => 'other',
        };
    }

    /**
     * Wat de koper ziet. Bekeken is unieke kijkers sinds publicatie.
     *
     * @return array{viewers: int, saved: int, shared: int, bidders: int, now: int, hot: bool}
     */
    public static function forBuyer(Lot $lot): array
    {
        return [
            'viewers' => self::count($lot, LotSignal::VIEWER),
            'saved' => self::count($lot, LotSignal::SAVED),
            'shared' => self::count($lot, LotSignal::SHARED),
            'bidders' => self::bidders($lot),
            'now' => self::watchingNow($lot),
            'hot' => (bool) $lot->is_hot,
        ];
    }

    /**
     * Wat de verkoper ziet: ook weergaven, documenten en herkomst.
     *
     * @return array{viewers: int, views: int, saved: int, shared: int, documents: int, bidders: int, sources: array<string, int>, score: int, hot: bool, window_hours: int}
     */
    public static function forSeller(Lot $lot): array
    {
        $sources = LotSignal::query()->where('lot_id', $lot->id)->where('kind', LotSignal::VIEWER)
            ->selectRaw("coalesce(source, 'direct') as source, count(*) as aantal")->groupBy('source')->pluck('aantal', 'source')
            ->map(fn ($count) => (int) $count)->all();

        return [
            'viewers' => self::count($lot, LotSignal::VIEWER),
            'views' => self::count($lot, LotSignal::VIEW),
            'saved' => self::count($lot, LotSignal::SAVED),
            'shared' => self::count($lot, LotSignal::SHARED),
            'documents' => self::count($lot, LotSignal::DOCUMENT),
            'bidders' => self::bidders($lot),
            'sources' => $sources,
            'score' => self::score($lot),
            'hot' => (bool) $lot->is_hot,
            'window_hours' => (int) config('aucvy.interest.window_hours', 48),
        ];
    }

    /**
     * De Hot-score over het venster: kijker 1, document 2, opgeslagen 3,
     * gedeeld 3, bieder 5.
     */
    public static function score(Lot $lot, ?int $hours = null): int
    {
        $since = now()->subHours($hours ?? (int) config('aucvy.interest.window_hours', 48));
        $weights = (array) config('aucvy.interest.weights', self::WEIGHTS);
        $score = 0;

        foreach ([LotSignal::VIEWER, LotSignal::DOCUMENT, LotSignal::SAVED, LotSignal::SHARED] as $kind) {
            $score += (int) ($weights[$kind] ?? 0) * self::count($lot, $kind, $since);
        }

        return $score + (int) ($weights['bidder'] ?? 0) * self::bidders($lot, $since);
    }

    public static function bidders(Lot $lot, ?DateTimeInterface $since = null): int
    {
        if ($lot->auction === null) {
            return 0;
        }

        return (int) Bid::query()->where('auction_id', $lot->auction->id)
            ->when($since !== null, fn ($query) => $query->where('placed_at', '>=', $since))
            ->distinct()->count('user_id');
    }

    public static function watchingNow(Lot $lot): int
    {
        return (int) LotSignal::query()->where('lot_id', $lot->id)->where('kind', LotSignal::VIEW)
            ->where('occurred_at', '>=', now()->subMinutes(5))->distinct()->count('visitor_hash');
    }

    private static function count(Lot $lot, string $kind, ?DateTimeInterface $since = null): int
    {
        $query = LotSignal::query()->where('lot_id', $lot->id)->where('kind', $kind)
            ->when($since !== null, fn ($query) => $query->where('occurred_at', '>=', $since));

        return $kind === LotSignal::VIEW ? (int) $query->count() : (int) $query->distinct()->count('visitor_hash');
    }
}
