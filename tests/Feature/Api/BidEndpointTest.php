<?php

declare(strict_types=1);

use App\Auctions\Exceptions\BidRejectionReason;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Company;
use App\Models\Lot;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->auction = Auction::factory()->create([
        'start_price_cents' => 100_000,
        'current_price_cents' => 100_000,
    ]);

    $this->bidder = User::factory()->verified()->create();
});

function bidUrl(Auction $auction): string
{
    return "/api/auctions/{$auction->id}/bids";
}

/**
 * @param  array<string, mixed>  $payload
 */
function postBid(Auction $auction, array $payload = [], ?string $key = null)
{
    return test()->postJson(
        bidUrl($auction),
        $payload + ['amount_cents' => 100_000],
        ['Idempotency-Key' => $key ?? (string) Str::uuid()],
    );
}

describe('toegang', function () {
    it('vraagt een account', function () {
        postBid($this->auction)->assertUnauthorized();
    });

    it('laat een ingelogde bieder door', function () {
        $this->actingAs($this->bidder);

        postBid($this->auction)->assertCreated();
    });
});

describe('invoer', function () {
    beforeEach(fn () => $this->actingAs($this->bidder));

    it('eist een idempotency-key', function () {
        /*
         | Bewust geen stille terugval op een willekeurige waarde: een client
         | die de header vergeet zou dan zonder waarschuwing dubbele biedingen
         | kunnen plaatsen (hfst. 7).
         */
        $this->postJson(bidUrl($this->auction), ['amount_cents' => 100_000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('idempotency_key');
    });

    it('eist een bedrag in hele centen', function () {
        $this->postJson(bidUrl($this->auction), ['amount_cents' => '1.000,50'], [
            'Idempotency-Key' => (string) Str::uuid(),
        ])->assertStatus(422)->assertJsonValidationErrors('amount_cents');
    });
});

describe('antwoord', function () {
    beforeEach(fn () => $this->actingAs($this->bidder));

    it('geeft de nieuwe stand plus het volgnummer terug', function () {
        // Zodat het biedpaneel niet hoeft na te vragen (hfst. 7).
        postBid($this->auction)
            ->assertCreated()
            ->assertJsonPath('bid.status', 'winning')
            ->assertJsonPath('bid.sequence', 1)
            ->assertJsonPath('state.price_cents', 100_000)
            ->assertJsonPath('state.sequence', 1)
            ->assertJsonPath('state.viewer.state', 'highest');
    });

    it('geeft bij dezelfde sleutel hetzelfde bod terug', function () {
        $key = (string) Str::uuid();

        $first = postBid($this->auction, key: $key)->assertCreated();
        $second = postBid($this->auction, key: $key)->assertCreated();

        expect($second->json('bid.id'))->toBe($first->json('bid.id'))
            ->and(Bid::count())->toBe(1);
    });
});

describe('geweigerde biedingen', function () {
    it('geeft 403 met een route naar verificatie', function () {
        $this->actingAs(User::factory()->create());

        postBid($this->auction)
            ->assertForbidden()
            ->assertJsonPath('reason', BidRejectionReason::NotVerified->value)
            ->assertJsonPath('resolution_route', 'account.verification');
    });

    it('geeft 422 met het minimumbedrag bij een te laag bod', function () {
        $this->actingAs($this->bidder);
        postBid($this->auction)->assertCreated();

        $this->actingAs(User::factory()->verified()->create());

        // Het biedpaneel moet een bedrag kunnen tonen, geen generieke fout.
        postBid($this->auction->fresh(), ['amount_cents' => 100_001])
            ->assertStatus(422)
            ->assertJsonPath('reason', BidRejectionReason::TooLow->value)
            ->assertJsonPath('minimum_cents', 105_000);
    });

    it('geeft 409 op een gesloten veiling', function () {
        $this->actingAs($this->bidder);

        postBid(Auction::factory()->closed()->create())
            ->assertStatus(409);
    });

    it('geeft 403 bij een bod op je eigen kavel', function () {
        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->live()->create(['seller_id' => $seller->id]);
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        $this->actingAs($seller);

        postBid($auction)
            ->assertForbidden()
            ->assertJsonPath('reason', BidRejectionReason::OwnLot->value);
    });

    it('geeft 403 bij een collega van hetzelfde bedrijf', function () {
        $company = Company::factory()->create();
        $seller = User::factory()->verified()->create(['company_id' => $company->id]);
        $colleague = User::factory()->verified()->create(['company_id' => $company->id]);
        $lot = Lot::factory()->live()->create([
            'seller_id' => $seller->id,
            'company_id' => $company->id,
        ]);
        $auction = Auction::factory()->create(['lot_id' => $lot->id]);

        $this->actingAs($colleague);

        postBid($auction)
            ->assertForbidden()
            ->assertJsonPath('reason', BidRejectionReason::SameCompany->value);
    });
});

describe('rate limiting', function () {
    it('knijpt af na tien pogingen in tien seconden', function () {
        /*
         | Per gebruiker én per veiling, niet per IP: een heel kantoor achter
         | één verbinding zou elkaar anders uitsluiten op precies het moment
         | dat een veiling sluit (hfst. 6).
         */
        $this->actingAs($this->bidder);

        // Bewust te lage bedragen: die worden geweigerd maar tellen wél mee.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            postBid($this->auction, ['amount_cents' => 1])->assertStatus(422);
        }

        postBid($this->auction, ['amount_cents' => 1])->assertStatus(429);
    });

    it('telt per veiling apart', function () {
        // Meebieden op drie kavels tegelijk is normaal gedrag.
        $this->actingAs($this->bidder);
        $other = Auction::factory()->create([
            'start_price_cents' => 100_000,
            'current_price_cents' => 100_000,
        ]);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            postBid($this->auction, ['amount_cents' => 1])->assertStatus(422);
        }

        postBid($other, ['amount_cents' => 1])->assertStatus(422);
    });
});
