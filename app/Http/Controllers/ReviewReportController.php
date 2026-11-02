<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewReport;
use App\Reviews\ReviewReason;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Een review melden (Google-principe): iedereen met een account kan het,
 * één keer per review. De review blijft staan tot een medewerker beslist;
 * de verkoper kan via een melding dus niets laten verdwijnen.
 */
final class ReviewReportController extends Controller
{
    public function store(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->isPublic(), 404);

        $validated = $request->validate([
            'reason' => ['required', Rule::enum(ReviewReason::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = ReviewReport::firstOrCreate(
            ['review_id' => $review->id, 'reporter_id' => $request->user()->id],
            ['reason' => $validated['reason'], 'note' => $validated['note'] ?? null],
        );

        if ($report->wasRecentlyCreated) {
            Audit::record('review.reported', $review, $request->user(), after: ['reason' => $report->reason->value], ip: $request->ip());
        }

        return back()->with('status', __('reviews.reported'));
    }
}
