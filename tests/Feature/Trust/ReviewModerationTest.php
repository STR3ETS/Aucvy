<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\User;
use App\Reviews\ReviewModerator;
use App\Reviews\ReviewReason;

/*
| Reviewmoderatie volgens het Google-principe (besluit Raph, 2 november
| 2026): automatische controle vóór publicatie, melden door iedereen,
| beheer beslist, de verkoper reageert maar verwijdert niets.
*/

function reviewableOrder(): Order
{
    return Order::factory()->completed()->create();
}

function publishedReview(?Order $order = null): Review
{
    $order ??= reviewableOrder();

    return Review::forceCreate([
        'order_id' => $order->id, 'reviewer_id' => $order->buyer_id, 'seller_id' => $order->seller_id,
        'company_id' => null, 'rating' => 2, 'body' => 'Kwam een week te laat en de deur zat er scheef in.',
        'published_at' => now(), 'moderation_status' => Review::STATUS_PUBLISHED,
    ]);
}

function moderationStaff(): User
{
    return User::factory()->create(['is_staff' => true]);
}

describe('de automatische controle', function () {
    it('houdt scheldwoorden, discriminatie, politiek en persoonsgegevens tegen', function (string $text, ReviewReason $reason) {
        expect(app(ReviewModerator::class)->reasonToHold($text))->toBe($reason);
    })->with([
        'scheldwoord' => ['Wat een kankerverkoper', ReviewReason::Offensive],
        'vermomd' => ['Echt een k4nker bedrijf', ReviewReason::Offensive],
        'kort scheldwoord als woord' => ['Wat een lul', ReviewReason::Offensive],
        'discriminatie' => ['Typisch zo een makaak', ReviewReason::Discrimination],
        'politiek' => ['Stem PVV en dit gebeurt niet', ReviewReason::Political],
        'telefoon' => ['Bel me op 06-12345678 voor details', ReviewReason::PersonalData],
        'e-mail' => ['Mail naar jan@voorbeeld.nl', ReviewReason::PersonalData],
    ]);

    it('laat gewone, ook kritische, reviews door', function (string $text) {
        expect(app(ReviewModerator::class)->reasonToHold($text))->toBeNull();
    })->with([
        'kritisch' => 'Kwam een week te laat, de deur zat scheef. Verkoper reageerde traag.',
        'spullen' => 'Alle spullen waren compleet en netjes ingepakt.',
        'denken' => 'Ik denk dat de prijs eerlijk was.',
        'volt' => 'Werkt prima op 230 volt.',
        'kutje woord in samenstelling' => 'De kuttelsteen is geen woord maar mag.',
        'leeg' => '',
    ]);
});

describe('een review plaatsen', function () {
    it('publiceert een gewone review direct', function () {
        $order = reviewableOrder();

        $this->actingAs($order->buyer)
            ->post(route('orders.review', $order), ['rating' => 4, 'body' => 'Netjes geleverd, goed contact.'])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('status', __('reviews.published'));

        $review = $order->review()->sole();

        expect($review->isPublic())->toBeTrue()
            ->and($review->moderation_status)->toBe(Review::STATUS_PUBLISHED);
    });

    it('houdt een review met een scheldwoord tegen en zegt dat eerlijk', function () {
        $order = reviewableOrder();

        $this->actingAs($order->buyer)
            ->post(route('orders.review', $order), ['rating' => 1, 'body' => 'Kankerbedrijf, nooit meer.'])
            ->assertRedirect(route('orders.show', $order));

        $review = $order->review()->sole();

        expect($review->isPublic())->toBeFalse()
            ->and($review->published_at)->toBeNull()
            ->and($review->moderation_status)->toBe(Review::STATUS_HELD)
            ->and($review->moderation_reason)->toBe(ReviewReason::Offensive)
            ->and(AuditLog::query()->where('action', 'review.held')->exists())->toBeTrue();

        $this->get(route('sellers.show', $order->seller))->assertOk()->assertDontSee('Kankerbedrijf');
        $this->actingAs($order->buyer)->get(route('orders.show', $order))->assertSee('Wordt bekeken door een medewerker');
    });
});

describe('melden', function () {
    it('laat iedereen met een account een review één keer melden, zonder dat hij verdwijnt', function () {
        $review = publishedReview();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('reviews.report', $review), ['reason' => 'not_a_transaction', 'note' => 'Dit gaat over een andere winkel.'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($stranger)->post(route('reviews.report', $review), ['reason' => 'offensive']);

        expect(ReviewReport::query()->where('review_id', $review->id)->count())->toBe(1)
            ->and($review->fresh()->isPublic())->toBeTrue();
    });

    it('laat de verkoper melden maar niets weghalen', function () {
        $review = publishedReview();

        $this->actingAs($review->seller)->post(route('reviews.report', $review), ['reason' => 'other'])->assertRedirect();

        expect($review->fresh()->isPublic())->toBeTrue();
        $this->actingAs($review->seller)->post(route('admin.reviews.remove', $review), ['reason' => 'other', 'note' => 'Ik wil hem weg'])->assertForbidden();
        expect($review->fresh()->isPublic())->toBeTrue();
    });

    it('weigert een melding op een review die niet online staat', function () {
        $review = publishedReview();
        $review->forceFill(['published_at' => null, 'moderation_status' => Review::STATUS_HELD])->save();

        $this->actingAs(User::factory()->create())->post(route('reviews.report', $review), ['reason' => 'other'])->assertNotFound();
    });
});

describe('het besluit van beheer', function () {
    it('toont tegengehouden en gemelde reviews bovenaan', function () {
        $held = publishedReview();
        $held->forceFill(['published_at' => null, 'moderation_status' => Review::STATUS_HELD, 'moderation_reason' => ReviewReason::Political, 'body' => 'Tegengehouden tekst'])->save();
        $reported = publishedReview();
        ReviewReport::create(['review_id' => $reported->id, 'reporter_id' => User::factory()->create()->id, 'reason' => 'offensive', 'note' => 'Grof']);
        publishedReview()->forceFill(['body' => 'Rustige review zonder melding'])->save();

        $response = $this->actingAs(moderationStaff())->get(route('admin.reviews'))->assertOk();

        expect($response->viewData('toReview')->pluck('id')->all())->toBe([$held->id, $reported->id]);
        $response->assertSee('Tegengehouden: politieke uitingen')->assertSee('Gemeld door');
    });

    it('publiceert een tegengehouden review alsnog, met spoor', function () {
        $review = publishedReview();
        $review->forceFill(['published_at' => null, 'moderation_status' => Review::STATUS_HELD, 'moderation_reason' => ReviewReason::Offensive])->save();

        $this->actingAs(moderationStaff())->post(route('admin.reviews.publish', $review))->assertRedirect();

        expect($review->fresh()->isPublic())->toBeTrue()
            ->and(AuditLog::query()->where('action', 'review.kept')->exists())->toBeTrue();
    });

    it('haalt een gemelde review offline met reden en motivering, en sluit de meldingen', function () {
        $review = publishedReview();
        $report = ReviewReport::create(['review_id' => $review->id, 'reporter_id' => User::factory()->create()->id, 'reason' => 'not_a_transaction']);

        $this->actingAs(moderationStaff())
            ->post(route('admin.reviews.remove', $review), ['reason' => 'not_a_transaction'])
            ->assertSessionHasErrors('note');

        $this->actingAs(moderationStaff())
            ->post(route('admin.reviews.remove', $review), ['reason' => 'not_a_transaction', 'note' => 'Gaat over een andere verkoper.'])
            ->assertRedirect();

        $review->refresh();

        expect($review->isPublic())->toBeFalse()
            ->and($review->moderation_status)->toBe(Review::STATUS_REMOVED)
            ->and($review->moderation_reason)->toBe(ReviewReason::NotATransaction)
            ->and($report->fresh()->handled_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', 'review.removed')->exists())->toBeTrue();

        $this->get(route('sellers.show', $review->seller))->assertOk()->assertDontSee('deur zat er scheef');
    });
});
