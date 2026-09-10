<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use App\Models\AiInsight;
use App\Models\AiRun;
use App\Models\Lot;
use App\Models\User;

/*
| WeeklyInsights (hfst. 8): de cijfers komen uit PHP, het model duidt ze, en
| de bevindingen landen als kaarten zonder knop in "Wat Ivy deze week zag".
| In tests draait de queue sync en de FakeProvider - deterministisch.
*/

it('schrijft wekelijkse bevindingen naar ai_insights voor elke verkoper', function () {
    $seller = User::factory()->verified()->create();
    Lot::factory()->live()->create(['seller_id' => $seller->id]);
    Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id]);

    $this->artisan('ivy:weekly-insights')->assertSuccessful();

    $run = AiRun::query()->where('task', 'weekly_insights')->sole();

    expect($run->status)->toBe(AiRunStatus::Completed)
        ->and($run->user_id)->toBe($seller->id);

    $weekly = AiInsight::query()->where('type', 'weekly')->get();

    expect($weekly)->not->toBeEmpty()
        ->and($weekly->first()->hasAction())->toBeFalse()
        ->and($weekly->first()->valid_until)->not->toBeNull();
});

it('vervangt de bevindingen van vorige week in plaats van te stapelen', function () {
    $seller = User::factory()->verified()->create();
    Lot::factory()->anonymousDraft()->create(['seller_id' => $seller->id]);

    AiInsight::create([
        'user_id' => $seller->id,
        'agent' => 'ivy',
        'type' => 'weekly',
        'title' => 'Oude bevinding van vorige week',
        'severity' => 'info',
        'valid_until' => now()->addDay(),
    ]);

    $this->artisan('ivy:weekly-insights')->assertSuccessful();

    expect(AiInsight::query()->where('type', 'weekly')->where('title', 'like', 'Oude%')->count())
        ->toBe(0);
});

it('slaat gebruikers zonder kavels over', function () {
    User::factory()->create();

    $this->artisan('ivy:weekly-insights')->assertSuccessful();

    expect(AiRun::query()->count())->toBe(0);
});

it('respecteert de noodrem ook hier', function () {
    config(['aucvy.ai.enabled' => false]);

    $seller = User::factory()->verified()->create();
    Lot::factory()->live()->create(['seller_id' => $seller->id]);

    $this->artisan('ivy:weekly-insights')->assertSuccessful();

    expect(AiRun::sole()->status)->toBe(AiRunStatus::Skipped)
        ->and(AiInsight::query()->where('type', 'weekly')->count())->toBe(0);
});
