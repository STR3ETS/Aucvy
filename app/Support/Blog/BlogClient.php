<?php

declare(strict_types=1);

namespace App\Support\Blog;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * De blog uit het CMS van Eazyonline (besluit Raph, 2 november 2026).
 *
 * Aucvy haalt op (GET /content?type=blog en GET /content/{slug}) met een
 * leestoken. Elk antwoord gaat twee keer de cache in: vers voor een
 * kwartier, en als reserve voor een week. Is de API even weg, dan toont de
 * blog de laatst bekende versie in plaats van een foutpagina.
 */
final class BlogClient
{
    private const STALE_DAYS = 7;

    public function configured(): bool
    {
        return (string) config('services.eazyonline.token') !== '';
    }

    /**
     * @return array{posts: list<array<string, mixed>>, page: int, last_page: int}
     */
    public function posts(int $page = 1, int $perPage = 12): array
    {
        $empty = ['posts' => [], 'page' => $page, 'last_page' => 1];

        if (! $this->configured()) {
            return $empty;
        }

        $body = $this->fetch("blog.list.{$page}.{$perPage}", '/content', ['type' => 'blog', 'page' => $page, 'per_page' => $perPage]);

        if ($body === null) {
            return $empty;
        }

        $items = array_is_list($body) ? $body : (array) ($body['data'] ?? []);

        return [
            'posts' => array_values(array_filter(array_map($this->post(...), $items))),
            'page' => (int) data_get($body, 'meta.current_page', $page),
            'last_page' => max(1, (int) data_get($body, 'meta.last_page', 1)),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $slug): ?array
    {
        if (! $this->configured()) {
            return null;
        }

        $body = $this->fetch("blog.post.{$slug}", '/content/'.rawurlencode($slug));

        if ($body === null) {
            return null;
        }

        $post = $this->post((array) ($body['data'] ?? $body));

        // Alleen blogberichten; een andere soort content heeft hier geen pagina.
        return $post === null || ($post['type'] ?? 'blog') !== 'blog' ? null : $post;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>|null
     */
    private function fetch(string $key, string $path, array $query = []): ?array
    {
        $fresh = Cache::get($key);

        if (is_array($fresh)) {
            return $fresh;
        }

        try {
            $response = Http::baseUrl((string) config('services.eazyonline.base_url'))
                ->withToken((string) config('services.eazyonline.token'))
                ->acceptJson()
                ->timeout(5)
                ->get($path, $query);

            if ($response->status() === 404) {
                return null;
            }

            $response->throw();
            $body = (array) $response->json();

            Cache::put($key, $body, now()->addMinutes((int) config('services.eazyonline.cache_minutes', 15)));
            Cache::put($key.'.stale', $body, now()->addDays(self::STALE_DAYS));

            return $body;
        } catch (Throwable $exception) {
            report($exception);

            $stale = Cache::get($key.'.stale');

            return is_array($stale) ? $stale : null;
        }
    }

    /**
     * Eén bericht in de vorm die de views verwachten; zonder titel of slug
     * is het geen bericht.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function post(array $item): ?array
    {
        $slug = (string) ($item['slug'] ?? '');

        if ($slug === '' || ! preg_match('/^[a-z0-9-]+$/', $slug) || ($item['title'] ?? '') === '') {
            return null;
        }

        return [
            'type' => $item['type'] ?? 'blog',
            'slug' => $slug,
            'title' => (string) $item['title'],
            'excerpt' => (string) ($item['excerpt'] ?? ''),
            'content' => isset($item['content']) ? SafeHtml::clean((string) $item['content']) : null,
            'image' => $this->httpUrl($item['featured_image'] ?? null),
            'image_alt' => (string) ($item['featured_image_alt'] ?? ''),
            'meta_title' => (string) ($item['meta_title'] ?? '') ?: (string) $item['title'],
            'meta_description' => (string) ($item['meta_description'] ?? '') ?: (string) ($item['excerpt'] ?? ''),
            'published_at' => $this->date($item['published_at'] ?? null),
            'updated_at' => $this->date($item['updated_at'] ?? null),
            'author' => is_array($item['author'] ?? null) ? (string) ($item['author']['name'] ?? '') : null,
        ];
    }

    private function httpUrl(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https?://#i', $url) === 1 ? $url : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
