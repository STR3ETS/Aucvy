<?php

declare(strict_types=1);

namespace App\Ivy\Tasks;

use App\Enums\AiAgent;
use App\Ivy\Contracts\AiTask;
use App\Models\AiRun;
use App\Models\IvyConversation;
use App\Models\IvyGuestUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Een eerste analyse op de eigen foto van een bezoeker (website-Ivy): wat
 * het is en de zichtbare staat, als voorbeeld en nooit als kavel. Staat
 * er een persoon op, dan vraagt Ivy om een andere foto.
 */
final class AnalyseGuestPhoto implements AiTask
{
    public function __construct(public IvyGuestUpload $upload, public IvyConversation $conversation) {}

    public function agent(): AiAgent
    {
        return AiAgent::Ivy;
    }

    public function name(): string
    {
        return 'analyse_guest_photo';
    }

    public function subject(): ?Model
    {
        // Het gesprek heeft een ULID; ai_runs.subject_id is een nummer. De koppeling staat in de invoer.
        return null;
    }

    public function input(): array
    {
        return ['upload_id' => $this->upload->id, 'checksum' => $this->upload->checksum, 'conversation_id' => $this->conversation->id];
    }

    public function prompt(): string
    {
        $schema = json_encode($this->schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}';

        return <<<PROMPT
            Een bezoeker stuurt een foto van iets dat hij misschien wil verkopen. Zeg wat het is en wat je van de staat ziet.
            Geen waarde noemen, geen garanties. Staat er een persoon herkenbaar op, zet dan person_visible op true en beschrijf niets.

            Antwoord met één JSON-object volgens dit schema, zonder tekst eromheen:
            {$schema}
            PROMPT;
    }

    public function images(): array
    {
        $path = Storage::disk('local')->path($this->upload->path);

        return is_file($path) ? [$path] : [];
    }

    public function documents(): array
    {
        return [];
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['object_type', 'confidence'],
            'properties' => [
                'object_type' => ['type' => 'string', 'maxLength' => 80],
                'condition' => ['type' => ['string', 'null'], 'maxLength' => 120],
                'person_visible' => ['type' => 'boolean'],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    public function apply(AiRun $run): void
    {
        $output = $run->output ?? [];

        $text = ($output['person_visible'] ?? false) === true
            ? 'Op deze foto staat een persoon. Stuur liever een foto van alleen het object, dan kijk ik ernaar.'
            : 'Ik zie: '.(string) ($output['object_type'] ?? 'een object').'.'.(isset($output['condition']) && $output['condition'] !== null ? ' Staat: '.$output['condition'].'.' : '').' Dit is een voorbeeld, geen kavel; met een account maak ik er een concept van met tekst en prijsadvies.';

        $this->upload->forceFill(['analysis' => ['text' => $text, 'object_type' => $output['object_type'] ?? null, 'condition' => $output['condition'] ?? null, 'confidence' => $output['confidence'] ?? null]])->save();
    }

    public function cacheKey(): string
    {
        return hash('sha256', $this->name().'|'.$this->upload->checksum);
    }
}
