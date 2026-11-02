<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use App\Enums\LotStatus;
use App\Enums\ValueConfidence;
use App\Ivy\AiResult;
use App\Ivy\Contracts\AiTask;
use App\Ivy\Contracts\LlmProvider;
use App\Ivy\Jobs\RunAiTask;
use App\Lots\Preflight;
use App\Lots\PublishLot;
use App\Models\AiRun;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\LotMedia;
use App\Models\User;
use App\Notifications\ListingScreened;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| Vooraf modereren (besluit Raph, 2 november 2026): Ivy controleert elke
| kavel op prijs, hoeveelheid met eenheid, garantie en documenten voordat
| hij live gaat. Ontbreekt er iets, dan terug naar de verkoper; twijfel over
| verboden waar of echtheid gaat naar een medewerker.
*/

beforeEach(function () {
    Queue::fakeExcept([RunAiTask::class]);
    Notification::fake();
    $this->seller = User::factory()->verified()->business()->create();
});

function screenableDraft(User $seller, array $overrides = []): Lot
{
    $lot = Lot::factory()->anonymousDraft()->create([
        'seller_id' => $seller->id,
        'title' => 'Compressor Atlas Copco GA11',
        'description' => 'Schroefcompressor uit een werkplaats, 11 kW, draait goed.',
        // Vast, niet uit de factory: een willekeurige merknaam kan op de
        // lijst met verboden waar aanslaan.
        'brand' => 'Atlas Copco',
        'model' => 'GA11',
        'value_min_cents' => null,
        'value_max_cents' => null,
        'value_confidence' => null,
        'category_id' => Category::factory()->create()->id,
        'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 150_000, 'ownership_declared' => true, 'warranty' => 'none'],
        ...$overrides,
    ]);

    LotMedia::factory()->create(['lot_id' => $lot->id]);

    return $lot;
}

/**
 * Een provider die voor de controle een vast oordeel teruggeeft.
 *
 * @param  array<string, mixed>  $output
 */
function screeningAnswers(array $output): void
{
    app()->instance(LlmProvider::class, new class($output) implements LlmProvider
    {
        /** @param  array<string, mixed>  $output */
        public function __construct(private readonly array $output) {}

        public function complete(AiTask $task, string $prompt): AiResult
        {
            $text = json_encode($task->name() === 'screen_listing' ? $this->output : ['confidence' => 0.5]) ?: '{}';

            return new AiResult(text: $text, model: 'fake', tokensIn: 10, tokensOut: 10);
        }
    });
}

it('zet een kavel die aan de criteria voldoet na de controle live', function () {
    $lot = screenableDraft($this->seller);

    $result = app(PublishLot::class)->handle($lot, $this->seller);

    expect($result->status)->toBe(LotStatus::Live)
        ->and($result->auction)->not->toBeNull()
        ->and($result->screening['verdict'])->toBe('approve')
        ->and($result->submitted_at)->not->toBeNull()
        ->and(AiRun::query()->where('task', 'screen_listing')->where('status', AiRunStatus::Completed)->exists())->toBeTrue();

    Notification::assertSentTo($this->seller, ListingScreened::class, fn (ListingScreened $notification) => $notification->outcome === ListingScreened::LIVE);
});

it('stuurt een kavel met verboden waar naar een medewerker, wat het model ook zegt', function () {
    $lot = screenableDraft($this->seller, ['title' => 'Partij vuurwerk uit opslag']);

    $result = app(PublishLot::class)->handle($lot, $this->seller)->fresh();

    expect($result->status)->toBe(LotStatus::PendingReview)
        ->and($result->screening['verdict'])->toBe('escalate')
        ->and($result->screening['issues'][0]['field'])->toBe('forbidden')
        ->and($result->auction)->toBeNull()
        ->and(AuditLog::query()->where('action', 'lot.escalated')->where('subject_id', $lot->id)->exists())->toBeTrue();

    Notification::assertSentTo($this->seller, ListingScreened::class, fn (ListingScreened $notification) => $notification->outcome === ListingScreened::ESCALATED);
});

it('stuurt een kavel terug naar de verkoper met de punten van Ivy', function () {
    screeningAnswers([
        'verdict' => 'return',
        'issues' => [['field' => 'unit', 'message' => 'De omschrijving noemt tien stuks, de hoeveelheid staat op één.']],
        'confidence' => 0.8,
    ]);
    $lot = screenableDraft($this->seller);

    $result = app(PublishLot::class)->handle($lot, $this->seller)->fresh();

    expect($result->status)->toBe(LotStatus::Draft)
        ->and($result->submitted_at)->toBeNull()
        ->and($result->screening['issues'][0]['message'])->toContain('tien stuks')
        ->and($result->draft_meta['start_price_cents'])->toBe(150_000);

    Notification::assertSentTo($this->seller, ListingScreened::class, fn (ListingScreened $notification) => $notification->outcome === ListingScreened::RETURNED);

    // Na aanpassen opnieuw indienen kan, en dan gaat hij live.
    screeningAnswers(['verdict' => 'approve', 'issues' => [], 'confidence' => 0.9]);

    expect(app(PublishLot::class)->handle($result, $this->seller)->status)->toBe(LotStatus::Live);
});

it('laat het model nooit soepeler zijn dan de vaste regels', function () {
    screeningAnswers(['verdict' => 'approve', 'issues' => [], 'confidence' => 0.99]);
    $lot = screenableDraft($this->seller, [
        'value_min_cents' => 100_000,
        'value_max_cents' => 200_000,
        'value_confidence' => ValueConfidence::High,
        'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 15_000_000, 'ownership_declared' => true, 'warranty' => 'none'],
    ]);

    $result = app(PublishLot::class)->handle($lot, $this->seller)->fresh();

    expect($result->status)->toBe(LotStatus::Draft)
        ->and($result->screening['issues'][0]['field'])->toBe('price');
});

it('maakt van een afkeuring zonder punten een zaak voor een medewerker', function () {
    screeningAnswers(['verdict' => 'return', 'issues' => [], 'confidence' => 0.6]);
    $lot = screenableDraft($this->seller);

    expect(app(PublishLot::class)->handle($lot, $this->seller)->fresh()->screening['verdict'])->toBe('escalate');
});

it('legt de kavel bij een medewerker als Ivy uit staat', function () {
    config(['aucvy.ai.enabled' => false]);
    $lot = screenableDraft($this->seller);

    $result = app(PublishLot::class)->handle($lot, $this->seller)->fresh();

    expect($result->status)->toBe(LotStatus::PendingReview)
        ->and($result->screening['verdict'])->toBe('escalate');
});

it('laat een medewerker een kavel in beoordeling live zetten of afwijzen', function () {
    $staff = User::factory()->create(['is_staff' => true]);
    $approve = app(PublishLot::class)->handle(screenableDraft($this->seller, ['title' => 'Replica lamp']), $this->seller)->fresh();
    $reject = app(PublishLot::class)->handle(screenableDraft($this->seller, ['title' => 'Gestolen fietsen']), $this->seller)->fresh();

    $this->actingAs($staff)->get('/admin/moderatie')
        ->assertOk()
        ->assertSee('Wacht op akkoord')
        ->assertSee('Replica lamp')
        ->assertSee('Lijkt te vallen onder');

    $this->actingAs($staff)->post(route('admin.moderation.approve', $approve))->assertRedirect();
    $this->actingAs($staff)->post(route('admin.moderation.reject', $reject), ['reason' => 'Herkomst is niet aangetoond.'])->assertRedirect();

    expect($approve->fresh()->status)->toBe(LotStatus::Live)
        ->and($approve->fresh()->auction)->not->toBeNull()
        ->and($reject->fresh()->status)->toBe(LotStatus::Cancelled)
        ->and(AuditLog::query()->where('action', 'lot.published')->where('subject_id', $approve->id)->value('after')['approved_by'])->toBe('staff');
});

it('eist hoeveelheid met eenheid, een garantieverklaring en verklaarde documenten', function () {
    $lot = screenableDraft($this->seller, [
        'unit' => null,
        'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 150_000, 'ownership_declared' => true],
    ]);
    LotDocument::factory()->create(['lot_id' => $lot->id]);

    $preflight = app(Preflight::class)->for($lot->fresh(), $this->seller);

    expect($preflight['blocking'])->toContain('unit', 'warranty', 'documents_authentic');
});

it('slaat garantie en eenheid op vanuit de wizard en toont ze op de kavelpagina', function () {
    $lot = screenableDraft($this->seller, ['quantity' => 4]);

    $this->actingAs($this->seller)
        ->patchJson("/api/drafts/{$lot->id}", ['unit' => 'pallet', 'warranty' => 'seller', 'warranty_until' => now()->addYear()->toDateString()])
        ->assertOk();

    $this->actingAs($this->seller)->postJson("/api/drafts/{$lot->id}/publish")
        ->assertOk()
        ->assertJsonPath('published', true);

    $live = $lot->fresh();

    expect($live->unit)->toBe('pallet')
        ->and($live->warranty)->toBe('seller')
        ->and($live->warranty_until)->not->toBeNull();

    $this->get(route('lots.show', $live))
        ->assertOk()
        ->assertSee('4 × '.config('listings.unit_options.pallet'))
        ->assertSee(config('listings.warranty_options.seller'));
});

it('gaat direct live als vooraf modereren uit staat', function () {
    config(['aucvy.listings.pre_moderation' => false]);
    $lot = screenableDraft($this->seller, ['title' => 'Partij vuurwerk uit opslag']);

    $result = app(PublishLot::class)->handle($lot, $this->seller);

    expect($result->status)->toBe(LotStatus::Live)
        ->and(AiRun::query()->where('task', 'screen_listing')->exists())->toBeFalse();
});
