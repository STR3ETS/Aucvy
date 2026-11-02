<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\MailDelivery;
use App\Support\Health\Health;
use Illuminate\View\View;

/**
 * De systeempagina in de backoffice: wat system:health ook ziet, plus de
 * laatste fouten uit het log en de instellingen die ertoe doen.
 */
final class SystemController extends Controller
{
    public function index(Health $health): View
    {
        $checks = $health->checks();

        return view('admin.system', [
            'checks' => $checks,
            'overall' => Health::overall($checks),
            'errors' => $health->recentErrors(20),
            'mail' => [
                'sent' => MailDelivery::query()->where('created_at', '>=', now()->subWeek())->count(),
                'delivered' => MailDelivery::query()->where('created_at', '>=', now()->subWeek())->where('status', MailDelivery::STATUS_DELIVERED)->count(),
                'problems' => MailDelivery::query()->where('created_at', '>=', now()->subWeek())->whereIn('status', MailDelivery::PROBLEMS)->count(),
                'recent' => MailDelivery::query()->latest('id')->limit(15)->get(),
            ],
            'facts' => [
                'Omgeving' => (string) app()->environment(),
                'PHP' => PHP_VERSION,
                'Laravel' => app()->version(),
                'Wachtrij' => (string) config('queue.default'),
                'Cache' => (string) config('cache.default'),
                'Mail' => (string) config('mail.default'),
                'Ivy-provider' => (string) config('aucvy.ai.provider'),
                'Betaalprovider' => (string) config('aucvy.payments.provider', 'fake'),
                'Tijdzone weergave' => (string) config('aucvy.display_timezone'),
            ],
        ]);
    }
}
