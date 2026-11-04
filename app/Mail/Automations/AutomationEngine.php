<?php

declare(strict_types=1);

namespace App\Mail\Automations;

use App\Mail\MailSender;
use App\Models\AutomationRun;
use App\Models\MailAutomation;
use App\Models\MailConsent;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * De reeksmotor: schrijft nieuwe runs in, en verwerkt per run de stap
 * waarvan de tijd is aangebroken. Idempotent: dezelfde stap twee keer
 * verwerken geeft één mail. Afmelden stopt lopende marketingreeksen.
 */
final class AutomationEngine
{
    public function __construct(private readonly MailSender $sender) {}

    /**
     * @return array{enrolled: int, processed: int, stopped: int}
     */
    public function tick(): array
    {
        AutomationCatalog::seed();

        $enrolled = 0;

        foreach (MailAutomation::query()->where('status', MailAutomation::ACTIVE)->get() as $automation) {
            foreach (AutomationCatalog::enrol($automation->key) as $candidate) {
                if (in_array($automation->stream, MailTemplate::MARKETING, true) && MailConsent::statusFor($candidate['user'], $automation->stream) === MailConsent::REVOKED) {
                    continue;
                }

                try {
                    AutomationRun::create([
                        'automation_id' => $automation->id,
                        'user_id' => $candidate['user']->id,
                        'lot_id' => $candidate['lot_id'],
                        'order_id' => $candidate['order_id'],
                        'current_step' => 1,
                        'next_run_at' => now(),
                        'status' => AutomationRun::RUNNING,
                        'dedupe_key' => $candidate['dedupe_key'],
                    ]);
                    $enrolled++;
                } catch (QueryException) {
                    // Al ingeschreven: één run per sleutel.
                }
            }
        }

        $processed = 0;
        $stopped = 0;

        foreach (AutomationRun::query()->due()->with(['automation', 'user', 'lot.auction', 'order'])->limit(500)->get() as $run) {
            $processed++;

            if ($this->process($run) === AutomationRun::STOPPED) {
                $stopped++;
            }
        }

        return compact('enrolled', 'processed', 'stopped');
    }

    /**
     * Verwerkt stappen tot de eerstvolgende wachtstap of het einde.
     */
    public function process(AutomationRun $run): string
    {
        $steps = (array) $run->automation->steps;

        while ($run->status === AutomationRun::RUNNING) {
            $reason = AutomationCatalog::stopReason($run);

            if ($reason !== null) {
                $run->stop($reason);

                break;
            }

            $step = $steps[$run->current_step] ?? ['type' => 'end'];

            switch ($step['type']) {
                case 'wait':
                    $run->forceFill(['current_step' => $run->current_step + 1, 'next_run_at' => now()->addHours((int) ($step['hours'] ?? 24))])->save();

                    return $run->status;
                case 'check':
                    if (! AutomationCatalog::check((string) ($step['rule'] ?? ''), $run)) {
                        $run->stop('goal_reached');

                        break 2;
                    }
                    $run->forceFill(['current_step' => $run->current_step + 1])->save();

                    break;
                case 'mail':
                    $outcome = $this->sender->send($run->user, (string) $step['template'], AutomationCatalog::context($run), $run);

                    if ($outcome !== null && ! in_array($outcome, ['already_sent'], true)) {
                        $run->stop('mail_'.$outcome);

                        break 2;
                    }
                    $run->forceFill(['current_step' => $run->current_step + 1])->save();

                    break;
                case 'task':
                    $run->forceFill(['current_step' => $run->current_step + 1])->save();

                    break;
                default:
                    $run->forceFill(['status' => AutomationRun::DONE, 'next_run_at' => null])->save();

                    break 2;
            }
        }

        return $run->status;
    }

    /**
     * Afmelden stopt elke lopende marketingreeks van deze klant.
     */
    public static function stopMarketingFor(User $user, string $reason = 'unsubscribed'): int
    {
        $count = 0;

        $runs = AutomationRun::query()->where('user_id', $user->id)->where('status', AutomationRun::RUNNING)
            ->whereHas('automation', fn ($query) => $query->whereIn('stream', MailTemplate::MARKETING))->get();

        foreach ($runs as $run) {
            $run->stop($reason);
            $count++;
        }

        return $count;
    }
}
