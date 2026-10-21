<?php

declare(strict_types=1);

namespace App\Lots\Management;

use App\Enums\LotStatus;
use App\Lots\Provenance;
use App\Lots\Translation;
use App\Models\Lot;
use App\Models\User;
use App\Support\Audit\Audit;
use BackedEnum;
use Illuminate\Validation\ValidationException;

/**
 * Een gepubliceerde kavel bewerken (briefing sectie 4: /studio/objecten/{id}).
 * Omschrijving, kenmerken en logistiek mogen altijd; de titel alleen zolang
 * er nog niet geboden is, want daar hebben bieders op geboden. Elke
 * wijziging staat in het auditlog met de oude en nieuwe waarde.
 */
final class UpdateLot
{
    /** @var list<string> */
    public const FIELDS = ['title', 'description', 'condition', 'brand', 'model', 'year', 'quantity', 'pickup_only', 'viewing_available', 'transport_from_cents'];

    /** @var list<LotStatus> */
    public const EDITABLE = [LotStatus::Scheduled, LotStatus::Live, LotStatus::Closing, LotStatus::Paused];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Lot $lot, User $seller, array $data): Lot
    {
        if (! in_array($lot->status, self::EDITABLE, true)) {
            throw ValidationException::withMessages(['lot' => __('lots.manage.not_editable')]);
        }

        $data = array_intersect_key($data, array_flip(self::FIELDS));

        if (array_key_exists('title', $data) && $data['title'] !== $lot->title && $this->hasBids($lot)) {
            throw ValidationException::withMessages(['title' => __('lots.manage.title_locked')]);
        }

        $before = [];
        $after = [];

        foreach ($data as $field => $value) {
            $current = $lot->{$field};
            $currentValue = $current instanceof BackedEnum ? $current->value : $current;
            // Het formulier levert tekst; vergelijk in het type van de kolom, anders
            // telt "3" tegenover 3 als wijziging en vult het auditlog zich met niets.
            $value = $this->normalise($currentValue, $value);

            if ($currentValue === $value) {
                continue;
            }

            $before[$field] = $currentValue;
            $after[$field] = $value;
            $lot->{$field} = $value;
        }

        if ($after === []) {
            return $lot;
        }

        $lot->save();

        Provenance::markSeller($lot, array_keys($after));
        Audit::record('lot.updated', $lot, $seller, $before, $after);

        // Nieuwe tekst, nieuwe vertaling; de oude vervalt vanzelf op zijn hash.
        if (array_key_exists('title', $after) || array_key_exists('description', $after)) {
            Translation::start($lot);
        }

        return $lot;
    }

    public function hasBids(Lot $lot): bool
    {
        return ($lot->auction?->bid_count ?? 0) > 0;
    }

    private function normalise(mixed $current, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match (true) {
            is_bool($current) => filter_var($value, FILTER_VALIDATE_BOOL),
            is_int($current) => (int) $value,
            is_float($current) => (float) $value,
            is_string($current) && is_string($value) => trim($value),
            default => $value,
        };
    }
}
