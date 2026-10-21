<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LotImport;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Delen van uploads die nooit zijn afgemaakt ruimen we na een dag op; de
 * csv- en xlsx-bestanden van imports na dertig dagen. Het rapport van een
 * afgeronde import blijft staan, alleen het bronbestand gaat weg.
 */
final class PruneChunkedUploads extends Command
{
    protected $signature = 'uploads:prune {--hours=24} {--import-days=30}';

    protected $description = 'Verwijdert onafgemaakte uploads in delen en oude importbestanden';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = CarbonImmutable::now()->subHours((int) $this->option('hours'));
        $removed = 0;

        $this->pruneImports((int) $this->option('import-days'));

        foreach ($disk->directories('chunks') as $lotDir) {
            foreach ($disk->directories($lotDir) as $uploadDir) {
                $meta = json_decode((string) $disk->get($uploadDir.'/meta.json'), true);
                $createdAt = isset($meta['created_at']) ? CarbonImmutable::parse((string) $meta['created_at']) : null;

                if ($createdAt === null || $createdAt->lessThan($cutoff)) {
                    $disk->deleteDirectory($uploadDir);
                    $removed++;
                }
            }

            if ($disk->directories($lotDir) === []) {
                $disk->deleteDirectory($lotDir);
            }
        }

        $this->info($removed.' onafgemaakte uploads opgeruimd.');

        return self::SUCCESS;
    }

    /**
     * Importbestanden ouder dan het aantal dagen weg; een import die nooit
     * is uitgevoerd verdwijnt helemaal, een afgeronde houdt zijn rapport.
     */
    private function pruneImports(int $days): void
    {
        $cutoff = CarbonImmutable::now()->subDays($days);
        $files = 0;
        $records = 0;

        LotImport::query()->where('created_at', '<', $cutoff)->orderBy('id')->chunkById(100, function ($imports) use (&$files, &$records): void {
            foreach ($imports as $import) {
                if ($import->path !== null && is_file($import->path)) {
                    @unlink($import->path);
                    $files++;
                }

                if ($import->isDone()) {
                    $import->forceFill(['path' => ''])->save();
                } else {
                    $import->delete();
                    $records++;
                }
            }
        });

        $this->info($files.' importbestanden en '.$records.' nooit uitgevoerde imports opgeruimd.');
    }
}
