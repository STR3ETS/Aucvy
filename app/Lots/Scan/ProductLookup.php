<?php

declare(strict_types=1);

namespace App\Lots\Scan;

use App\Lots\Provenance;
use App\Models\Lot;
use App\Models\ProductLookup as Lookup;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Productopzoeking op EAN: eerst de eigen cache, dan de EAN-dienst (Go-UPC
 * als de sleutel er is). De uitkomst toont altijd de bron; merk en model
 * komen als voorstel op de kavel en de verkoper bevestigt. Productteksten
 * en afbeeldingen van de dienst gaan niet publiek voordat de licentie is
 * gecontroleerd; ze blijven een hint in het concept.
 */
final class ProductLookup
{
    public const string SOURCE = 'ean';

    /**
     * @return array{status: string, provider: string, name: string|null, brand: string|null, model: string|null, description: string|null, image: string|null, source: string|null}
     */
    public function lookup(string $gtin): array
    {
        $cached = Lookup::query()->where('gtin', $gtin)->first();

        if ($cached !== null && ($cached->status !== Lookup::NO_PROVIDER || ! $this->hasProvider())) {
            return $this->shape($cached);
        }

        if (! $this->hasProvider()) {
            return $this->shape($this->remember($gtin, 'none', Lookup::NO_PROVIDER, null));
        }

        try {
            $response = Http::timeout(8)->withToken((string) config('services.go_upc.key'))->get('https://go-upc.com/api/v1/code/'.$gtin);
        } catch (Throwable) {
            return $this->shape($this->remember($gtin, 'go_upc', Lookup::NOT_FOUND, ['error' => 'unreachable']));
        }

        if (! $response->successful() || ! is_array($response->json('product'))) {
            return $this->shape($this->remember($gtin, 'go_upc', Lookup::NOT_FOUND, null));
        }

        $product = (array) $response->json('product');

        return $this->shape($this->remember($gtin, 'go_upc', Lookup::FOUND, [
            'name' => Str::limit((string) ($product['name'] ?? ''), 160, ''),
            'brand' => Str::limit((string) ($product['brand'] ?? ''), 80, ''),
            'description' => Str::limit((string) ($product['description'] ?? ''), 600, ''),
            'image' => is_string($product['imageUrl'] ?? null) ? $product['imageUrl'] : null,
            'category' => (string) ($product['category'] ?? ''),
        ]));
    }

    /**
     * Zet de uitkomst op het concept: EAN als kenmerk, merk en model als
     * voorstel met bron en zekerheid, de productnaam als hint.
     *
     * @param  array<string, mixed>  $result
     */
    public function applyTo(Lot $lot, string $gtin, array $result): void
    {
        $lot = $lot->fresh();

        if ($lot === null || ! $lot->isDraft()) {
            return;
        }

        $specifications = $lot->specifications ?? [];
        $specifications['ean'] = ['value' => $gtin, 'source' => 'scan', 'confidence' => 1.0];
        $lot->specifications = $specifications;
        $lot->gtin = $gtin;

        $confidence = [];

        if ($result['status'] === Lookup::FOUND) {
            if (($result['brand'] ?? '') !== '' && ! Provenance::isHuman($lot, 'brand') && ($lot->brand === null || $lot->brand === '')) {
                $lot->brand = $result['brand'];
                // Onder de drempel: data van een externe dienst bevestigt de verkoper altijd zelf.
                $confidence['brand'] = 0.7;
            }

            $model = $this->modelFrom($result);

            if ($model !== null && ! Provenance::isHuman($lot, 'model') && ($lot->model === null || $lot->model === '')) {
                $lot->model = $model;
                $confidence['model'] = 0.6;
            }

            $meta = $lot->draft_meta ?? [];
            $meta['ean_product'] = ['name' => $result['name'], 'description' => $result['description'], 'image' => $result['image'], 'source' => $result['source'], 'licence_checked' => false];
            $lot->draft_meta = $meta;
        }

        $lot->save();

        if ($confidence !== []) {
            Provenance::markAi($lot, $confidence, self::SOURCE, null, true, ['provider' => $result['source']]);
        }
    }

    public function hasProvider(): bool
    {
        return (string) config('services.go_upc.key') !== '';
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function remember(string $gtin, string $provider, string $status, ?array $payload): Lookup
    {
        return Lookup::query()->updateOrCreate(['gtin' => $gtin], ['provider' => $provider, 'status' => $status, 'payload' => $payload, 'fetched_at' => now()]);
    }

    /**
     * @return array{status: string, provider: string, name: string|null, brand: string|null, model: string|null, description: string|null, image: string|null, source: string|null}
     */
    private function shape(Lookup $lookup): array
    {
        $payload = (array) ($lookup->payload ?? []);

        return [
            'status' => $lookup->status,
            'provider' => $lookup->provider,
            'name' => $payload['name'] ?? null,
            'brand' => $payload['brand'] ?? null,
            'model' => $this->modelFrom(['name' => $payload['name'] ?? null, 'brand' => $payload['brand'] ?? null]),
            'description' => $payload['description'] ?? null,
            'image' => $payload['image'] ?? null,
            'source' => $lookup->provider === 'go_upc' ? 'Go-UPC' : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function modelFrom(array $result): ?string
    {
        $name = trim((string) ($result['name'] ?? ''));
        $brand = trim((string) ($result['brand'] ?? ''));

        if ($name === '') {
            return null;
        }

        $model = $brand !== '' && Str::startsWith(Str::lower($name), Str::lower($brand)) ? trim(Str::substr($name, Str::length($brand))) : $name;

        return $model === '' ? null : Str::limit($model, 80, '');
    }
}
