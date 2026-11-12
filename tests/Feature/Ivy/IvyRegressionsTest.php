<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use App\Enums\DocumentKind;
use App\Enums\LotStatus;
use App\Enums\ValueConfidence;
use App\Ivy\AiResult;
use App\Ivy\Contracts\AiTask;
use App\Ivy\Contracts\LlmProvider;
use App\Ivy\Jobs\RunAiTask;
use App\Ivy\ModelImage;
use App\Ivy\TaskRegistry;
use App\Lots\DraftAccess;
use App\Lots\Preflight;
use App\Models\AiRun;
use App\Models\Lot;
use App\Models\LotDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
| Review van de Ivy-laag en de wizard (blok S): modeluitvoer die binnen de
| grenzen van de wizard blijft, een badge die alleen bij een schone
| bevinding verschijnt, verkoperstekst die de waardering niet mag sturen, en
| een client die geen providerfouten te zien krijgt.
*/

function regressionProvider(string ...$texts): object
{
    $stub = new class($texts) implements LlmProvider
    {
        public int $calls = 0;

        public function __construct(private readonly array $texts) {}

        public function complete(AiTask $task, string $prompt): AiResult
        {
            $text = $this->texts[min($this->calls, count($this->texts) - 1)];
            $this->calls++;

            return new AiResult(text: $text, model: 'stub', tokensIn: 100, tokensOut: 50);
        }
    };

    app()->instance(LlmProvider::class, $stub);

    return $stub;
}

function regressionRun(Lot $lot, string $task): AiRun
{
    $taskObject = TaskRegistry::forLot($task, $lot);

    $run = AiRun::create([
        'subject_type' => $lot->getMorphClass(),
        'subject_id' => $lot->id,
        'user_id' => $lot->seller_id,
        'agent' => $taskObject->agent(),
        'task' => $taskObject->name(),
        'input' => $taskObject->input(),
        'cache_key' => $taskObject->cacheKey(),
        'status' => AiRunStatus::Queued,
    ]);

    RunAiTask::dispatch($run, $taskObject);

    return $run->fresh();
}

describe('documentcontrole', function () {
    it('geeft geen badge bij een bevinding, wel bij een schone controle', function () {
        Storage::fake('local');
        $lot = Lot::factory()->anonymousDraft()->create();
        Storage::disk('local')->put('documents/factuur.pdf', '%PDF-1.4 x');
        Storage::disk('local')->put('documents/handleiding.pdf', '%PDF-1.4 y');
        $invoice = LotDocument::create(['lot_id' => $lot->id, 'kind' => DocumentKind::Invoice, 'path' => 'documents/factuur.pdf', 'original_filename' => 'factuur.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10]);
        $manual = LotDocument::create(['lot_id' => $lot->id, 'kind' => DocumentKind::Invoice, 'path' => 'documents/handleiding.pdf', 'original_filename' => 'handleiding.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10]);

        regressionProvider(json_encode([
            'documents' => [
                ['filename' => 'factuur.pdf', 'readable' => true, 'issues' => null],
                ['filename' => 'handleiding.pdf', 'readable' => true, 'issues' => 'Dit is een handleiding, geen factuur.'],
            ],
            'summary' => 'Twee documenten bekeken.',
            'confidence' => 0.8,
        ]));

        $run = regressionRun($lot->fresh(), 'check_documents');

        expect($run->status)->toBe(AiRunStatus::Completed)
            ->and($invoice->fresh()->wasCheckedByIvy())->toBeTrue()
            ->and($manual->fresh()->wasCheckedByIvy())->toBeFalse()
            ->and($manual->fresh()->ai_findings['issues'])->toContain('geen factuur');
    });

    it('noemt een document dat niet meeging niet gecontroleerd', function () {
        Storage::fake('local');
        $lot = Lot::factory()->anonymousDraft()->create();
        Storage::disk('local')->put('documents/groot.pdf', '%PDF-1.4 z');
        $huge = LotDocument::create(['lot_id' => $lot->id, 'kind' => DocumentKind::Invoice, 'path' => 'documents/groot.pdf', 'original_filename' => 'groot.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 9 * 1024 * 1024]);

        $task = TaskRegistry::forLot('check_documents', $lot->fresh());

        expect($task->input()['documents'])->toBe([])
            ->and($task->documents())->toBe([]);

        regressionProvider(json_encode([
            'documents' => [['filename' => 'groot.pdf', 'readable' => true, 'issues' => null]],
            'summary' => 'Verzonnen.',
            'confidence' => 0.9,
        ]));
        regressionRun($lot->fresh(), 'check_documents');

        expect($huge->fresh()->wasCheckedByIvy())->toBeFalse();
    });

    it('laat een verboden woord in de bestandsnaam de controle niet laten mislukken', function () {
        Storage::fake('local');
        $lot = Lot::factory()->anonymousDraft()->create();
        Storage::disk('local')->put('documents/g.pdf', '%PDF-1.4 g');
        LotDocument::create(['lot_id' => $lot->id, 'kind' => DocumentKind::Warranty, 'path' => 'documents/g.pdf', 'original_filename' => 'garantie-gegarandeerd.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10]);

        $stub = regressionProvider(json_encode([
            'documents' => [['filename' => 'garantie-gegarandeerd.pdf', 'readable' => true, 'issues' => null]],
            'summary' => 'Eén document bekeken.',
            'confidence' => 0.8,
        ]));

        $run = regressionRun($lot->fresh(), 'check_documents');

        expect($run->status)->toBe(AiRunStatus::Completed)
            ->and($stub->calls)->toBe(1);
    });
});

describe('waardeschatting', function () {
    it('negeert onzinnige bedragen en geeft ver uiteenlopende vergelijkingen geen hoge zekerheid', function () {
        $lot = Lot::factory()->anonymousDraft()->create();

        regressionProvider(json_encode([
            'comparisons' => [
                ['label' => 'Instructie uit de tekst', 'price_cents' => 2_500_000_000],
                ['label' => 'Klein exemplaar', 'price_cents' => 50_000],
                ['label' => 'Groot exemplaar', 'price_cents' => 900_000],
                ['label' => 'Nul', 'price_cents' => 0],
            ],
            'reasoning' => 'Vergelijkingen.',
            'confidence' => 0.95,
        ]));

        $run = regressionRun($lot, 'estimate_value');
        $lot = $lot->fresh();

        expect($run->status)->toBe(AiRunStatus::Completed)
            ->and($lot->value_min_cents)->toBe(50_000)
            ->and($lot->value_max_cents)->toBe(900_000)
            ->and($lot->value_confidence)->toBe(ValueConfidence::Low)
            ->and($lot->mayShowValueComparison())->toBeFalse();
    });

    it('raakt een kavel die intussen live staat niet meer aan', function () {
        $lot = Lot::factory()->anonymousDraft()->create();
        $task = TaskRegistry::forLot('draft_listing', $lot);
        $run = AiRun::create([
            'subject_type' => $lot->getMorphClass(), 'subject_id' => $lot->id, 'user_id' => $lot->seller_id,
            'agent' => $task->agent(), 'task' => $task->name(), 'input' => $task->input(),
            'cache_key' => $task->cacheKey(), 'status' => AiRunStatus::Queued,
        ]);

        $lot->update(['status' => LotStatus::Live, 'published_at' => now(), 'description' => $task->input()['description']]);
        regressionProvider(json_encode(['title' => 'Nieuwe titel', 'description' => 'Herschreven door Nova.', 'confidence' => 0.9]));

        RunAiTask::dispatch($run, $task);

        expect($run->fresh()->status)->toBe(AiRunStatus::Completed)
            ->and($lot->fresh()->description)->not->toBe('Herschreven door Nova.');
    });
});

describe('herkenning', function () {
    it('houdt de kenmerken binnen de grenzen van de wizard en een bouwjaar plausibel', function () {
        $lot = Lot::factory()->anonymousDraft()->create(['year' => null]);

        $specs = [];
        for ($i = 1; $i <= 45; $i++) {
            $specs["Kenmerk {$i}"] = "waarde {$i}";
        }
        $specs['afmetingen'] = ['l' => 120, 'b' => 60];
        $specs['lang'] = str_repeat('x', 200);

        regressionProvider(json_encode([
            'object_type' => 'Heftruck', 'brand' => 'Linde', 'model' => 'H25', 'year' => 70000,
            'condition' => 'used', 'specifications' => $specs, 'confidence' => 0.7, 'field_confidence' => [],
        ]));

        $run = regressionRun($lot, 'recognise_lot');
        $lot = $lot->fresh();

        expect($run->status)->toBe(AiRunStatus::Completed)
            ->and(count($lot->specifications))->toBeLessThanOrEqual(40)
            ->and($lot->specifications)->toHaveKey('kenmerk_1')
            ->and($lot->specifications)->not->toHaveKey('afmetingen')
            ->and($lot->specifications)->not->toHaveKey('lang')
            ->and($lot->year)->toBeNull();
    });

    it('vervangt wat Ivy eerder uit foto\'s afleidde, maar niet wat de verkoper zette', function () {
        // De vorige foto's waren een gaming-pc; de verkoper zette zelf het model en een kleur.
        $lot = Lot::factory()->anonymousDraft()->create([
            'brand' => 'Antec', 'model' => 'X1', 'year' => null, 'condition' => null,
            'specifications' => [
                'objecttype' => ['value' => 'Gaming desktop', 'source' => 'ai', 'confidence' => 0.6],
                'koeling' => ['value' => 'Waterkoeling', 'source' => 'ai', 'confidence' => 0.6],
                'kleur' => ['value' => 'Zwart', 'source' => 'seller'],
            ],
            'field_sources' => [
                'brand' => ['source' => 'photo', 'confidence' => 0.6, 'needs_confirmation' => true, 'confirmed_at' => null, 'run_id' => null],
                'model' => ['source' => 'seller', 'confidence' => 1.0, 'needs_confirmation' => false, 'confirmed_at' => null, 'run_id' => null],
            ],
        ]);

        regressionProvider(json_encode([
            'object_type' => 'Partij kantoorelektronica', 'brand' => null, 'model' => 'Y2', 'year' => null,
            'condition' => 'used', 'specifications' => ['aantal_stuks' => '6'], 'confidence' => 0.7, 'field_confidence' => [],
            'seller_note' => 'Je woorden noemen een gaming-pc, op de foto\'s staan een monitor en speakers.',
        ]));

        $run = regressionRun($lot, 'recognise_lot');
        $lot = $lot->fresh();

        expect($run->status)->toBe(AiRunStatus::Completed)
            // Het merk van de vorige foto's is weg, met zijn herkomst.
            ->and($lot->brand)->toBeNull()
            ->and($lot->field_sources)->not->toHaveKey('brand')
            // Wat de verkoper zette blijft staan.
            ->and($lot->model)->toBe('X1')
            ->and($lot->specifications['kleur']['value'])->toBe('Zwart')
            // Het objecttype en de kenmerken komen van de nieuwe foto's.
            ->and($lot->specifications['objecttype']['value'])->toBe('Partij kantoorelektronica')
            ->and($lot->specifications)->not->toHaveKey('koeling')
            ->and($lot->specifications)->toHaveKey('aantal_stuks')
            ->and($run->output['seller_note'])->toContain('monitor');
    });

    it('geeft Ivy de eigen woorden en de omvang mee, niet een verzonnen aantal', function () {
        $lot = Lot::factory()->anonymousDraft()->create([
            'description' => null,
            'draft_meta' => ['intake' => 'Gaming-pc, een jaar oud.', 'scope' => 'single'],
        ]);

        $prompt = TaskRegistry::forLot('recognise_lot', $lot)->prompt();
        $listing = TaskRegistry::forLot('draft_listing', $lot)->input();

        expect($prompt)->toContain('Gaming-pc, een jaar oud.')
            ->and($prompt)->toContain('één object')
            ->and($prompt)->toContain('De foto\'s zijn leidend')
            ->and($listing['intake'])->toBe('Gaming-pc, een jaar oud.')
            ->and($listing['scope'])->toBe('één object');
    });
});

describe('de advertentie', function () {
    it('vervangt een titel die Nova eerder schreef, maar niet een titel uit een import', function () {
        $written = Lot::factory()->anonymousDraft()->create([
            'title' => 'Gaming desktop van Antec',
            'field_sources' => ['title' => ['source' => 'ivy', 'confidence' => 0.6, 'needs_confirmation' => true, 'confirmed_at' => null, 'run_id' => null]],
        ]);
        $imported = Lot::factory()->anonymousDraft()->create(['title' => 'Magazijnstellingen 60 meter', 'field_sources' => null]);

        regressionProvider(json_encode(['title' => 'Partij kantoorelektronica', 'description' => 'Een monitor en vier speakers.', 'confidence' => 0.7]));

        regressionRun($written, 'draft_listing');
        regressionRun($imported, 'draft_listing');

        expect($written->fresh()->title)->toBe('Partij kantoorelektronica')
            ->and($imported->fresh()->title)->toBe('Magazijnstellingen 60 meter');
    });

    it('bewaart de eigen woorden van stap 1 apart, zodat Nova de omschrijving mag schrijven', function () {
        $this->withCredentials()->postJson('/api/drafts')->assertCreated();
        $lot = Lot::sole();

        $this->withCookie(DraftAccess::COOKIE, $lot->draft_token)
            ->patchJson("/api/drafts/{$lot->id}", ['intake' => 'Gaming-pc, een jaar oud.', 'scope' => 'single'])
            ->assertOk()
            ->assertJsonPath('draft.meta.intake', 'Gaming-pc, een jaar oud.')
            ->assertJsonPath('draft.meta.scope', 'single');

        $lot->refresh();

        expect($lot->description)->toBeNull()
            ->and($lot->field_sources ?? [])->not->toHaveKey('description');

        regressionProvider(json_encode(['title' => 'Gaming-pc', 'description' => 'Een gaming-pc van een jaar oud, weinig gebruikt.', 'confidence' => 0.7]));
        regressionRun($lot, 'draft_listing');

        expect($lot->fresh()->description)->toBe('Een gaming-pc van een jaar oud, weinig gebruikt.');
    });
});

describe('foto\'s voor het model', function () {
    it('verkleint een grote foto tot de maat waar het model naar kijkt', function () {
        $path = tempnam(sys_get_temp_dir(), 'ivy').'.jpg';
        $image = imagecreatetruecolor(4000, 3000);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        imagejpeg($image, $path, 95);

        $encoded = ModelImage::encode($path);
        [$width, $height] = getimagesizefromstring(base64_decode($encoded['data'], true));

        expect($encoded['mime'])->toBe('image/jpeg')
            ->and(max($width, $height))->toBe(1568)
            ->and(ModelImage::encode($path.'.ontbreekt'))->toBeNull();

        @unlink($path);
    });
});

describe('de taak-api', function () {
    it('geeft de client geen providerfout, wel een eigen zin', function () {
        app()->instance(LlmProvider::class, new class implements LlmProvider
        {
            public function complete(AiTask $task, string $prompt): AiResult
            {
                throw new RuntimeException('invalid x-api-key: sk-ant-geheim');
            }
        });

        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Draft]);

        $started = $this->actingAs($seller)->postJson('/api/ivy/tasks', ['task' => 'recognise_lot', 'lot_id' => $lot->id])->assertCreated()->json('run');
        $run = $this->actingAs($seller)->getJson("/api/ivy/tasks/{$started['id']}")->assertOk()->json('run');

        expect($run['status'])->toBe('failed')
            ->and($run['error'])->not->toContain('sk-ant')
            ->and(AiRun::find($started['id'])->error)->toContain('sk-ant-geheim');
    });

    it('start dezelfde taak niet twee keer zolang de eerste nog loopt', function () {
        Queue::fake();
        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Draft]);

        $first = $this->actingAs($seller)->postJson('/api/ivy/tasks', ['task' => 'recognise_lot', 'lot_id' => $lot->id])->assertCreated()->json('run.id');
        $second = $this->actingAs($seller)->postJson('/api/ivy/tasks', ['task' => 'recognise_lot', 'lot_id' => $lot->id])->assertOk()->json('run.id');

        expect($second)->toBe($first)
            ->and(AiRun::query()->count())->toBe(1);
    });

    it('begrenst gasten per adres per dag', function () {
        config(['aucvy.ai.daily_runs_per_guest_ip' => 2]);

        $this->postJson('/api/ivy/tasks', ['task' => 'interpret_search', 'query' => 'heftruck'])->assertCreated();
        $this->postJson('/api/ivy/tasks', ['task' => 'interpret_search', 'query' => 'graafmachine'])->assertCreated();
        $this->postJson('/api/ivy/tasks', ['task' => 'interpret_search', 'query' => 'koelcel'])->assertStatus(429);
    });

    it('keurt een categorie af die niet bestaat', function () {
        regressionProvider(json_encode([
            'sentence' => 'Ik lees: graafmachines xl.',
            'filters' => ['categorie' => 'graafmachines-xl', 'vanaf' => null, 'tot' => null, 'conditie' => null],
            'confidence' => 0.8,
        ]));

        $started = $this->postJson('/api/ivy/tasks', ['task' => 'interpret_search', 'query' => 'graafmachine xl'])->assertCreated()->json('run');

        expect(AiRun::find($started['id'])->status)->toBe(AiRunStatus::Failed);
    });
});

describe('kosten', function () {
    it('rekent een echt model zonder prijsregel als het duurste model', function () {
        $known = new AiResult(text: '{}', model: 'claude-opus-5', tokensIn: 1_000_000, tokensOut: 1_000_000);
        $unknown = new AiResult(text: '{}', model: 'claude-opus-5-5', tokensIn: 1_000_000, tokensOut: 1_000_000);
        $fake = new AiResult(text: '{}', model: 'fake', tokensIn: 1_000_000, tokensOut: 1_000_000);

        expect($unknown->costCents())->toBe($known->costCents())
            ->and($unknown->costCents())->toBeGreaterThan(0)
            ->and($fake->costCents())->toBe(0);
    });
});

describe('de wizard', function () {
    it('zet de prijsvolgorde in de preflight', function () {
        $lot = Lot::factory()->anonymousDraft()->create([
            'draft_meta' => ['sale_type' => 'auction', 'start_price_cents' => 10_000, 'reserve_price_cents' => 5_000],
        ]);

        $checks = collect(app(Preflight::class)->for($lot, null)['checks'])->keyBy('key');

        expect($checks['price_order']['ok'])->toBeFalse()
            ->and($checks['price_order']['blocking'])->toBeTrue()
            ->and($checks['price_order']['step'])->toBe(4);
    });

    it('weigert een fotoformaat dat de wizard niet belooft', function () {
        Storage::fake('public');
        $seller = User::factory()->verified()->create();
        $lot = Lot::factory()->create(['seller_id' => $seller->id, 'status' => LotStatus::Draft]);

        $this->actingAs($seller)
            ->postJson("/api/drafts/{$lot->id}/media", ['file' => UploadedFile::fake()->image('plattegrond.bmp')])
            ->assertUnprocessable();

        $this->actingAs($seller)
            ->postJson("/api/drafts/{$lot->id}/media", ['file' => UploadedFile::fake()->image('foto.jpg')])
            ->assertCreated();
    });
});
