<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Reviews\ReviewReason;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Het besluit over een tegengehouden of gemelde review: publiceren (de
 * review klopt) of verwijderen met een reden. Altijd met spoor in het
 * auditlog, en de meldingen worden afgesloten. Alleen beheer kan dit; de
 * verkoper niet.
 */
final class ReviewModerationController extends Controller
{
    public function publish(Request $request, Review $review): RedirectResponse
    {
        DB::transaction(function () use ($request, $review): void {
            $before = $review->only(['moderation_status', 'moderation_reason']);

            $review->forceFill([
                'moderation_status' => Review::STATUS_PUBLISHED,
                'moderation_reason' => null,
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
                'published_at' => $review->published_at ?? now(),
            ])->save();

            $this->closeReports($review);

            Audit::record('review.kept', $review, $request->user(), before: $before, after: ['moderation_status' => Review::STATUS_PUBLISHED], ip: $request->ip());
        });

        $this->forgetScores($review);

        return back()->with('status', 'De review staat online.');
    }

    public function remove(Request $request, Review $review): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::enum(ReviewReason::class)],
            'note' => ['required', 'string', 'max:1000'],
        ], [
            'note.required' => 'Schrijf kort op waarom de review weg moet; dat gaat in het auditlog.',
        ]);

        DB::transaction(function () use ($request, $review, $validated): void {
            $before = $review->only(['moderation_status', 'moderation_reason', 'published_at']);

            $review->forceFill([
                'moderation_status' => Review::STATUS_REMOVED,
                'moderation_reason' => $validated['reason'],
                'moderation_note' => $validated['note'],
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
                'published_at' => null,
            ])->save();

            $this->closeReports($review);

            Audit::record('review.removed', $review, $request->user(), before: $before, after: [
                'moderation_status' => Review::STATUS_REMOVED,
                'reason' => $validated['reason'],
                'note' => $validated['note'],
            ], ip: $request->ip());
        });

        $this->forgetScores($review);

        return back()->with('status', 'De review is offline gehaald.');
    }

    private function closeReports(Review $review): void
    {
        ReviewReport::query()->where('review_id', $review->id)->whereNull('handled_at')->update(['handled_at' => now()]);
    }

    /**
     * De sterren en de Trust Index rekenen met gepubliceerde reviews; na een
     * besluit niet tien minuten op een oude stand blijven staan.
     */
    private function forgetScores(Review $review): void
    {
        Cache::forget("trust:{$review->seller_id}");
        Cache::forget("trust:profile:{$review->seller_id}");
    }
}
