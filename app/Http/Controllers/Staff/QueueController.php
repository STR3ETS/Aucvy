<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * De wachtrij in de backoffice (briefing sectie 8: queues en dead-letter):
 * wat er wacht per queue, wat er mislukte en waarom, en opnieuw proberen of
 * opruimen zonder terminal. Dit is de dead-letter-bak op de database-driver;
 * Horizon hoort bij Redis en is een hostingkeuze.
 */
final class QueueController extends Controller
{
    public function index(): View
    {
        return view('admin.queue', [
            'pending' => $this->pending(),
            'failedTotal' => DB::table('failed_jobs')->count(),
            'failedToday' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count(),
            'failedByJob' => $this->failedByJob(),
            'failed' => $this->recentFailed(),
        ]);
    }

    public function retry(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'uuid' => ['nullable', 'string', 'max:64'],
        ]);

        $uuid = $validated['uuid'] ?? null;

        Artisan::call('queue:retry', ['id' => [$uuid ?? 'all']]);
        Audit::record('queue.retried', $request->user(), $request->user(), after: ['uuid' => $uuid ?? 'all'], ip: $request->ip());

        return redirect()->route('admin.queue')->with('status', $uuid === null ? 'Alle mislukte jobs staan opnieuw in de wachtrij.' : 'De job staat opnieuw in de wachtrij.');
    }

    public function forget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'uuid' => ['required', 'string', 'max:64'],
        ]);

        Artisan::call('queue:forget', ['id' => $validated['uuid']]);
        Audit::record('queue.forgotten', $request->user(), $request->user(), after: ['uuid' => $validated['uuid']], ip: $request->ip());

        return redirect()->route('admin.queue')->with('status', 'De mislukte job is verwijderd.');
    }

    public function flush(Request $request): RedirectResponse
    {
        $count = DB::table('failed_jobs')->count();

        Artisan::call('queue:flush');
        Audit::record('queue.flushed', $request->user(), $request->user(), after: ['count' => $count], ip: $request->ip());

        return redirect()->route('admin.queue')->with('status', $count.' mislukte jobs opgeruimd.');
    }

    /**
     * @return Collection<int, array{queue: string, total: int, oldest: int|null}>
     */
    private function pending(): Collection
    {
        return DB::table('jobs')
            ->selectRaw('queue, count(*) as total, min(available_at) as oldest')
            ->groupBy('queue')
            ->orderBy('queue')
            ->get()
            ->map(fn (object $row): array => [
                'queue' => (string) $row->queue,
                'total' => (int) $row->total,
                'oldest' => $row->oldest !== null ? (int) $row->oldest : null,
            ]);
    }

    /**
     * @return Collection<int, array{job: string, total: int, last: string}>
     */
    private function failedByJob(): Collection
    {
        return DB::table('failed_jobs')
            ->selectRaw("json_unquote(json_extract(payload, '$.displayName')) as job, count(*) as total, max(failed_at) as last")
            ->groupBy('job')
            ->orderByDesc('total')
            ->limit(12)
            ->get()
            ->map(fn (object $row): array => [
                'job' => (string) ($row->job ?? 'onbekend'),
                'total' => (int) $row->total,
                'last' => (string) $row->last,
            ]);
    }

    /**
     * @return Collection<int, array{uuid: string, queue: string, job: string, reason: string, failed_at: string}>
     */
    private function recentFailed(): Collection
    {
        return DB::table('failed_jobs')
            ->select(['uuid', 'queue', 'payload', 'exception', 'failed_at'])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (object $row): array {
                $payload = json_decode((string) $row->payload, true);

                return [
                    'uuid' => (string) $row->uuid,
                    'queue' => (string) $row->queue,
                    'job' => (string) ($payload['displayName'] ?? 'onbekend'),
                    'reason' => mb_substr(strtok((string) $row->exception, "\n") ?: '', 0, 220),
                    'failed_at' => (string) $row->failed_at,
                ];
            });
    }
}
