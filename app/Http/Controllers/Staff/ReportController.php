<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Admin\Reports;
use App\Http\Controllers\Controller;
use App\Models\AdminAccessLog;
use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapportages (control center): vaste rapporten als csv met een link die
 * na een dag verloopt, en een wekelijkse of maandelijkse mail. Elke export
 * staat in admin_access_logs.
 */
final class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports', [
            'reports' => Reports::KEYS,
            'schedules' => ReportSchedule::query()->latest('id')->get(),
            'defaultFrom' => now()->subMonth()->startOfMonth()->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    public function run(Request $request, Reports $reports): RedirectResponse
    {
        $validated = $request->validate([
            'report' => ['required', Rule::in(Reports::KEYS)],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from'])->startOfDay();
        $to = CarbonImmutable::parse($validated['to'])->endOfDay();
        $path = $reports->store($validated['report'], $from, $to);

        AdminAccessLog::record($request->user(), 'export', $validated['report'].':'.$from->toDateString().':'.$to->toDateString(), null, $request->ip());

        return redirect()->route('admin.reports')
            ->with('status', __('admin.reports.'.$validated['report']).' staat klaar; de link werkt 24 uur.')
            ->with('export_url', $reports->signedUrl($path));
    }

    public function download(Request $request): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $path = (string) $request->query('path', '');

        abort_unless(str_starts_with($path, 'exports/rapport-') && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, basename($path), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'report' => ['required', Rule::in(Reports::KEYS)],
            'frequency' => ['required', Rule::in(['weekly', 'monthly'])],
            'recipients' => ['required', 'string', 'max:500'],
        ]);

        $recipients = collect(preg_split('/[\s,;]+/', $validated['recipients']) ?: [])
            ->map(fn ($address) => mb_strtolower(trim((string) $address)))
            ->filter(fn ($address) => filter_var($address, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()->values();

        if ($recipients->isEmpty()) {
            return back()->withErrors(['recipients' => 'Geef minstens één geldig e-mailadres.']);
        }

        ReportSchedule::create([
            'report_key' => $validated['report'],
            'frequency' => $validated['frequency'],
            'recipients' => $recipients->all(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.reports')->with('status', 'Rapport ingepland.');
    }

    public function destroySchedule(ReportSchedule $schedule): RedirectResponse
    {
        $schedule->delete();

        return redirect()->route('admin.reports')->with('status', 'Planning verwijderd.');
    }
}
