<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Lots\LotFilters;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Ivy's "ik bewaak de markt voor je", en de uitweg uit een lege
 * zoekresultatenpagina (hfst. 13.2).
 */
#[Fillable(['user_id', 'name', 'query', 'interpreted', 'channel', 'last_run_at', 'paused_at'])]
class SavedSearch extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'query' => 'array',
            'interpreted' => 'array',
            'channel' => NotificationChannel::class,
            'last_run_at' => 'datetime',
            'paused_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('paused_at');
    }

    /**
     * De bewaarde querystring terug als filters, via dezelfde parser als de
     * zoekpagina: wat daar niet werkt, werkt hier ook niet.
     */
    public function filters(): LotFilters
    {
        return LotFilters::fromRequest(Request::create('/veilingen', 'GET', $this->query ?? []));
    }

    public function label(): string
    {
        if ($this->name !== null && trim($this->name) !== '') {
            return $this->name;
        }

        $filters = $this->filters();
        $parts = array_filter([
            $filters->query,
            $filters->category !== null ? Category::query()->where('slug', $filters->category)->value('name') ?? $filters->category : null,
        ]);

        return $parts === [] ? 'Alle kavels' : implode(' in ', $parts);
    }

    /**
     * De filters in gewone taal, voor de lijst.
     */
    public function summary(): string
    {
        $filters = $this->filters();
        $parts = [];

        if ($filters->query !== null) {
            $parts[] = '"'.$filters->query.'"';
        }
        if ($filters->category !== null) {
            $parts[] = 'categorie '.$filters->category;
        }
        if ($filters->saleTypes !== []) {
            $parts[] = implode(', ', $filters->saleTypes);
        }
        if ($filters->conditions !== []) {
            $parts[] = 'conditie '.implode(', ', $filters->conditions);
        }
        if ($filters->provinces !== []) {
            $parts[] = implode(', ', $filters->provinces);
        }
        if ($filters->priceMinCents !== null || $filters->priceMaxCents !== null) {
            $parts[] = 'prijs '.($filters->priceMinCents !== null ? '€ '.intdiv($filters->priceMinCents, 100) : '').' tot '.($filters->priceMaxCents !== null ? '€ '.intdiv($filters->priceMaxCents, 100) : 'onbeperkt');
        }
        if ($filters->viewingOnly) {
            $parts[] = 'met kijkmoment';
        }
        if ($filters->transportOnly) {
            $parts[] = 'met transport';
        }

        return $parts === [] ? 'zonder filters' : implode(' · ', $parts);
    }
}
