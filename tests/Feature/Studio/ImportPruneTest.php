<?php

declare(strict_types=1);

use App\Models\LotImport;
use App\Models\User;

/*
| Oude importbestanden gaan na dertig dagen weg: het rapport van een
| afgeronde import blijft, een nooit uitgevoerde import verdwijnt.
*/

it('ruimt oude importbestanden op en houdt het rapport van afgeronde imports', function () {
    $user = User::factory()->create();
    $oldFile = tempnam(sys_get_temp_dir(), 'aucvy-import');
    $doneFile = tempnam(sys_get_temp_dir(), 'aucvy-import');
    $freshFile = tempnam(sys_get_temp_dir(), 'aucvy-import');

    $stale = LotImport::create(['user_id' => $user->id, 'filename' => 'oud.csv', 'path' => $oldFile, 'headers' => ['Titel'], 'rows_total' => 1]);
    $done = LotImport::create(['user_id' => $user->id, 'filename' => 'klaar.csv', 'path' => $doneFile, 'headers' => ['Titel'], 'rows_total' => 1, 'rows_created' => 1, 'status' => LotImport::DONE]);
    $fresh = LotImport::create(['user_id' => $user->id, 'filename' => 'nieuw.csv', 'path' => $freshFile, 'headers' => ['Titel'], 'rows_total' => 1]);

    LotImport::query()->whereIn('id', [$stale->id, $done->id])->update(['created_at' => now()->subDays(40)]);

    $this->artisan('uploads:prune')->assertSuccessful();

    expect(is_file($oldFile))->toBeFalse()
        ->and(is_file($doneFile))->toBeFalse()
        ->and(is_file($freshFile))->toBeTrue()
        ->and(LotImport::query()->find($stale->id))->toBeNull()
        ->and($done->fresh()->path)->toBe('')
        ->and($done->fresh()->rows_created)->toBe(1)
        ->and($fresh->fresh()->path)->toBe($freshFile);

    @unlink($freshFile);
});
