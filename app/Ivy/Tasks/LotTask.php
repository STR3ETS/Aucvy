<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\MediaType;
use App\Ivy\Contracts\AiTask;
use App\Models\Lot;
use App\Models\LotMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Gedeelde basis voor taken die over een kavel gaan.
 *
 * SerializesModels: de taak reist mee in de queue-payload en mag daar niet
 * het hele kavelrecord in meenemen - alleen de verwijzing.
 */
abstract class LotTask implements AiTask
{
    use SerializesModels;

    public function __construct(public Lot $lot) {}

    public function subject(): Model
    {
        return $this->lot;
    }

    /**
     * @return list<string>
     */
    public function images(): array
    {
        return $this->lot->media
            ->where('type', MediaType::Image)
            ->map(fn (LotMedia $media): string => Storage::disk('public')->path((string) ($media->variants['original'] ?? '')))
            ->filter(fn (string $path): bool => is_file($path))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function documents(): array
    {
        return [];
    }

    public function cacheKey(): string
    {
        // sha256 past precies in de 64 tekens van `ai_runs.cache_key`.
        return hash('sha256', json_encode([
            $this->name(),
            (int) config('aucvy.ai.prompt_version'),
            $this->input(),
        ], JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * De checksums maken de cachesleutel foto-precies: andere foto's met
     * hetzelfde aantal zijn een andere analyse.
     *
     * @return list<string>
     */
    protected function photoChecksums(): array
    {
        return $this->lot->media
            ->where('type', MediaType::Image)
            ->pluck('checksum')
            ->values()
            ->all();
    }
}
