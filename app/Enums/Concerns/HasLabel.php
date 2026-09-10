<?php

declare(strict_types=1);

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Geeft een enum een Nederlandse weergavetekst uit lang/nl/enums.php.
 *
 * Code, kolommen en enumwaarden blijven Engels; alleen wat de gebruiker leest
 * is Nederlands (briefing hfst. 4). De vertaalsleutel wordt afgeleid van de
 * klassenaam: LotStatus::Draft -> enums.lot_status.draft
 */
trait HasLabel
{
    public function label(): string
    {
        return __(static::translationKey().'.'.$this->value);
    }

    /**
     * Bruikbaar voor een <select> of een filterlijst.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            static::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(static::cases(), 'value');
    }

    protected static function translationKey(): string
    {
        return 'enums.'.Str::snake(class_basename(static::class));
    }
}
