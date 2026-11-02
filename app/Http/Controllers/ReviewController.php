<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use App\Reviews\ReviewModerator;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Reviews zijn niet cosmetisch (hfst. 13.6): alleen een afgeronde order
 * geeft er recht op, kritische reviews blijven staan - de verkoper heeft
 * bewust geen delete-route - en de verkoper krijgt een reactieveld. Wat
 * offline gaat, gaat dat via beheer (ReviewModerationController).
 */
final class ReviewController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()->id === $order->buyer_id, 404);

        // De technische regel, niet een redactionele: status `completed`.
        if (! $order->canBeReviewed()) {
            throw ValidationException::withMessages(['review' => __('reviews.errors.not_completed')]);
        }

        if ($order->review()->exists()) {
            throw ValidationException::withMessages(['review' => __('reviews.errors.already_reviewed')]);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = new Review([
            'order_id' => $order->id,
            'reviewer_id' => $request->user()->id,
            'seller_id' => $order->seller_id,
            'company_id' => $order->seller->company_id,
            'rating' => $validated['rating'],
            'body' => $validated['body'] ?? null,
        ]);

        /*
         | Google-principe (besluit Raph, 2 november 2026): eerst de
         | automatische controle. Geen treffer, dan direct publiek. Een
         | treffer (scheldwoord, discriminatie, politiek, persoonsgegevens)
         | wordt niet gepubliceerd maar door een medewerker beoordeeld; de
         | koper hoort dat eerlijk, met de reden.
         */
        $held = app(ReviewModerator::class)->reasonToHold($review->body);

        if ($held === null) {
            $review->published_at = now();
            $review->moderation_status = Review::STATUS_PUBLISHED;
        } else {
            $review->moderation_status = Review::STATUS_HELD;
            $review->moderation_reason = $held;
        }

        $review->save();

        if ($held !== null) {
            Audit::record('review.held', $review, null, after: ['reason' => $held->value]);

            return redirect()->route('orders.show', $order)->with('status', __('reviews.held', ['reason' => $held->label()]));
        }

        return redirect()->route('orders.show', $order)->with('status', __('reviews.published'));
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        abort_unless($request->user()->id === $review->seller_id, 404);

        if ($review->seller_reply !== null) {
            throw ValidationException::withMessages(['reply' => __('reviews.errors.already_replied')]);
        }

        $validated = $request->validate([
            'reply' => ['required', 'string', 'max:2000'],
        ]);

        $review->seller_reply = $validated['reply'];
        $review->seller_replied_at = now();
        $review->save();

        return back();
    }
}
