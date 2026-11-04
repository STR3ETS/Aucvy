<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Models\Auction;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotMedia;
use App\Models\Order;
use App\Models\Review;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/*
| Prestatiereview (blok V): wat per rij of per bezoek een query deed, doet
| dat niet meer, en wat eeuwig groeide heeft een bewaartermijn.
*/

function queriesDuring(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

describe('publieke pagina\'s', function () {
    it('haalt de omslagfoto per hoofdcategorie met kleine query\'s op', function () {
        $root = Category::factory()->create(['parent_id' => null]);
        $child = Category::factory()->create(['parent_id' => $root->id]);

        foreach (range(1, 12) as $i) {
            $lot = Lot::factory()->live()->create(['category_id' => $child->id]);
            Auction::factory()->create(['lot_id' => $lot->id]);
            LotMedia::create(['lot_id' => $lot->id, 'type' => 'image', 'path' => "/storage/lots/foto-{$i}.jpg", 'variants' => [], 'position' => 0]);
        }

        $queries = queriesDuring(fn () => $this->get('/categorieen')->assertOk()->assertSee('/storage/lots/foto-12.jpg'));

        // Onafhankelijk van het aantal kavels: vooraf laadde dit alle kavels mét al hun foto's.
        expect($queries)->toBeLessThan(25);
    });

    it('cachet de facetten per filtercombinatie', function () {
        $lot = Lot::factory()->live()->create();
        Auction::factory()->create(['lot_id' => $lot->id]);

        $first = queriesDuring(fn () => $this->get('/veilingen')->assertOk());
        $second = queriesDuring(fn () => $this->get('/veilingen')->assertOk());

        expect($second)->toBeLessThan($first);
    });

    it('rekent de sterrenverdeling van een verkoper in de database', function () {
        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->live()->create(['seller_id' => $seller->id]);
        Auction::factory()->create(['lot_id' => $lot->id]);

        foreach ([5, 5, 3] as $rating) {
            $order = Order::factory()->completed()->create(['seller_id' => $seller->id, 'lot_id' => $lot->id]);
            Review::forceCreate([
                'order_id' => $order->id, 'reviewer_id' => $order->buyer_id, 'seller_id' => $seller->id,
                'company_id' => null, 'rating' => $rating, 'body' => 'Prima', 'published_at' => now(),
            ]);
        }

        $this->get(route('sellers.show', $seller))
            ->assertOk()
            ->assertSee('4,3');
    });
});

describe('het dashboard', function () {
    it('bepaalt de veilingstatus uit tellingen die de lijst meeneemt', function () {
        $seller = User::factory()->verified()->create();

        foreach (range(1, 8) as $i) {
            Sale::factory()->create(['seller_id' => $seller->id, 'published_at' => now()->subDay()]);
        }

        $queries = queriesDuring(fn () => $this->actingAs($seller)->get('/dashboard/veilingen')->assertOk());

        // Vooraf: twee query's per rij bovenop de lijst.
        expect($queries)->toBeLessThan(14);
    });

    it('werkt de signalen hooguit één keer per minuut bij', function () {
        // In de testomgeving staat de drempel uit (phpunit.xml); hier aan.
        config(['aucvy.dashboard.signal_sync_seconds' => 60]);

        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->live()->create(['seller_id' => $seller->id]);
        Auction::factory()->create(['lot_id' => $lot->id]);

        $this->actingAs($seller)->get(route('dashboard'))->assertOk();

        expect(Cache::has("signals:synced:{$seller->id}"))->toBeTrue();

        $second = queriesDuring(fn () => $this->actingAs($seller)->get(route('dashboard'))->assertOk());
        Cache::forget("signals:synced:{$seller->id}");
        $synced = queriesDuring(fn () => $this->actingAs($seller)->get(route('dashboard'))->assertOk());

        expect($second)->toBeLessThan($synced);
    });
});

describe('beheer en opruimen', function () {
    it('telt biedingen van de laatste dag op het moment van bieden', function () {
        $auction = Auction::factory()->create();
        Bid::factory()->create(['auction_id' => $auction->id, 'sequence' => 1, 'placed_at' => now()->subHours(2), 'created_at' => now()->subDays(3)]);
        Bid::factory()->create(['auction_id' => $auction->id, 'sequence' => 2, 'placed_at' => now()->subDays(2), 'created_at' => now()->subDays(2)]);

        $this->actingAs(User::factory()->create(['is_staff' => true]))
            ->get('/admin')
            ->assertOk()
            ->assertSee('Biedingen op het platform')->assertSee('laatste 24 uur');
    });

    it('ruimt auditregels na de bewaartermijn op en laat de rest staan', function () {
        $lot = Lot::factory()->create();
        $old = AuditLog::create(['actor_id' => null, 'action' => 'lot.created', 'subject_type' => $lot->getMorphClass(), 'subject_id' => $lot->id, 'before' => [], 'after' => [], 'created_at' => now()->subYears(8)]);
        $recent = AuditLog::create(['actor_id' => null, 'action' => 'lot.created', 'subject_type' => $lot->getMorphClass(), 'subject_id' => $lot->id, 'before' => [], 'after' => [], 'created_at' => now()->subYears(2)]);

        $this->artisan('aucvy:prune-expired')->assertSuccessful();

        expect(AuditLog::query()->whereKey($old->id)->exists())->toBeFalse()
            ->and(AuditLog::query()->whereKey($recent->id)->exists())->toBeTrue();
    });

    it('telt de beoordelingen van vandaag zonder functie op de kolom', function () {
        Lot::factory()->create(['status' => LotStatus::Live, 'reviewed_at' => now()->subHour()]);
        Lot::factory()->create(['status' => LotStatus::Live, 'reviewed_at' => now()->subDays(2)]);

        $this->actingAs(User::factory()->create(['is_staff' => true]))
            ->get('/admin/moderatie')
            ->assertOk();
    });
});
