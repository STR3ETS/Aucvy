<?php

declare(strict_types=1);

namespace App\Support\Health;

use App\Enums\AiRunStatus;
use App\Enums\LotStatus;
use App\Models\AiRun;
use App\Models\Dispute;
use App\Models\ErrorEvent;
use App\Models\Lot;
use App\Models\MailDelivery;
use App\Models\Review;
use App\Risk\RiskScan;

/**
 * Ivy-statusmeldingen op het beheeroverzicht (besluit Raph, 2 november
 * 2026: "zoals Izzy bij Eazy"). Per aanvraag berekend uit wat er nu
 * speelt, zonder eigen opslag: systeemchecks, het foutlog, kavels die op
 * een medewerker wachten, vastgehouden reviews, geschillen en risico.
 *
 * Gesorteerd op ernst; het overzicht toont de bovenste drie.
 */
final class IvySignals
{
    private const RANK = ['fail' => 3, 'warn' => 2, 'info' => 1];

    public function __construct(private readonly Health $health) {}

    /**
     * @return list<array{level: 'fail'|'warn'|'info', title: string, text: string, url: string}>
     */
    public function all(): array
    {
        $signals = [];

        foreach ($this->health->checks() as $check) {
            if ($check['status'] !== 'ok' && $check['key'] !== 'errors') {
                $signals[] = [
                    'level' => $check['status'],
                    'title' => $check['label'],
                    'text' => trim($check['value'].($check['hint'] ? '. '.$check['hint'] : '')),
                    'url' => route('admin.system'),
                ];
            }
        }

        $openErrors = ErrorEvent::query()->open()->count();

        if ($openErrors > 0) {
            $today = ErrorEvent::query()->open()->where('first_seen_at', '>=', now()->subDay())->count();
            $signals[] = [
                'level' => $today > 0 ? 'fail' : 'warn',
                'title' => trans_choice('{1} 1 open fout|[2,*] :count open fouten', $openErrors, ['count' => $openErrors]),
                'text' => $today > 0 ? "Waarvan {$today} nieuw in de laatste 24 uur. Bekijk en handel af in het foutlog." : 'Geen nieuwe in de laatste 24 uur. Handel ze af zodra ze verholpen zijn.',
                'url' => route('admin.errors'),
            ];
        }

        $waiting = Lot::query()->where('status', LotStatus::PendingReview)->whereNotNull('screening')->count();

        if ($waiting > 0) {
            $signals[] = [
                'level' => 'warn',
                'title' => trans_choice('{1} 1 kavel wacht op akkoord|[2,*] :count kavels wachten op akkoord', $waiting, ['count' => $waiting]),
                'text' => 'Ik kon deze niet zelf goedkeuren. De verkoper wacht op een medewerker.',
                'url' => route('admin.moderation'),
            ];
        }

        $failedRuns = AiRun::query()->where('status', AiRunStatus::Failed)->where('created_at', '>=', now()->subDay())->count();

        if ($failedRuns >= 5) {
            $signals[] = [
                'level' => 'warn',
                'title' => "{$failedRuns} mislukte Ivy-taken in 24 uur",
                'text' => 'Meer dan gewoonlijk. Kijk bij de AI-runs naar de foutmeldingen.',
                'url' => route('admin.ai-runs'),
            ];
        }

        $bounced = MailDelivery::query()->whereIn('status', MailDelivery::PROBLEMS)->where('last_event_at', '>=', now()->subDay())->count();

        if ($bounced > 0) {
            $signals[] = [
                'level' => $bounced >= 10 ? 'fail' : 'warn',
                'title' => trans_choice('{1} 1 mail kwam niet aan|[2,*] :count mails kwamen niet aan', $bounced, ['count' => $bounced]),
                'text' => 'Gebounced of als spam gemeld in de laatste 24 uur. Bij veel tegelijk: controleer het domein bij Mailgun.',
                'url' => route('admin.system').'#mail',
            ];
        }

        $disputes = Dispute::query()->whereNull('resolved_at')->count();

        if ($disputes > 0) {
            $signals[] = [
                'level' => 'info',
                'title' => trans_choice('{1} 1 open geschil|[2,*] :count open geschillen', $disputes, ['count' => $disputes]),
                'text' => 'Koper en verkoper wachten op een uitspraak.',
                'url' => route('staff.disputes.index'),
            ];
        }

        $heldReviews = Review::query()->where('moderation_status', Review::STATUS_HELD)->count();

        if ($heldReviews > 0) {
            $signals[] = [
                'level' => 'info',
                'title' => trans_choice('{1} 1 review vastgehouden|[2,*] :count reviews vastgehouden', $heldReviews, ['count' => $heldReviews]),
                'text' => 'Mogelijk kwetsend of met persoonsgegevens. Publiceer of verwijder.',
                'url' => route('admin.reviews'),
            ];
        }

        $risk = app(RiskScan::class)->count();

        if ($risk > 0) {
            $signals[] = [
                'level' => 'info',
                'title' => trans_choice('{1} 1 risicosignaal|[2,*] :count risicosignalen', $risk, ['count' => $risk]),
                'text' => 'Patronen die op misbruik kunnen wijzen.',
                'url' => route('admin.risk'),
            ];
        }

        usort($signals, fn (array $a, array $b): int => self::RANK[$b['level']] <=> self::RANK[$a['level']]);

        return $signals;
    }
}
