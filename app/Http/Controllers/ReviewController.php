<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Reviews zijn niet cosmetisch (hfst. 13.6): alleen een afgeronde order
 * geeft er recht op, kritische reviews blijven staan - er bestaat hier
 * bewust geen delete-route - en de verkoper krijgt een reactieveld.
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

        // Direct publiek - er is geen redactieslag en die komt er ook niet.
        $review->published_at = now();
        $review->save();

        return redirect()->route('orders.show', $order);
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
