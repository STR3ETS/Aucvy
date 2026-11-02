<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Lot;
use App\Models\Sale;
use App\Support\Blog\BlogClient;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * robots.txt en sitemap.xml, dynamisch zodat de sitemap de publieke kavels
 * en categorieën volgt en robots.txt de juiste absolute URL noemt.
 */
final class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /dashboard',
            'Disallow: /bestellingen',
            'Disallow: /verificatie',
            'Disallow: /admin',
            'Disallow: /beheer',
            'Disallow: /dev',
            'Disallow: /api',
            'Disallow: /ab-test',
            '',
            'Sitemap: '.route('sitemap'),
            '',
        ];

        return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, fn (): string => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private function build(): string
    {
        $entries = [];

        foreach ([
            ['home', 'daily', '1.0'],
            ['lots.index', 'hourly', '0.9'],
            ['categories.index', 'daily', '0.8'],
            ['how-it-works', 'monthly', '0.6'],
            ['business', 'monthly', '0.6'],
            ['pricing', 'monthly', '0.6'],
            ['lots.buy-now', 'hourly', '0.8'],
            ['contact', 'yearly', '0.3'],
            ['selling.guide', 'monthly', '0.5'],
            ['faq', 'monthly', '0.5'],
            ['assurance', 'monthly', '0.5'],
            ['sell', 'monthly', '0.5'],
        ] as [$name, $frequency, $priority]) {
            $entries[] = $this->entry(route($name), null, $frequency, $priority);
        }

        Category::query()->orderBy('position')->get()->each(function (Category $category) use (&$entries): void {
            $entries[] = $this->entry(route('lots.category', $category), $category->updated_at?->toAtomString(), 'daily', '0.7');
        });

        Lot::query()
            ->public()
            ->whereNotNull('slug')
            ->latest('published_at')
            ->limit(5000)
            ->get(['id', 'slug', 'updated_at'])
            ->each(function (Lot $lot) use (&$entries): void {
                $entries[] = $this->entry(route('lots.show', $lot), $lot->updated_at?->toAtomString(), 'hourly', '0.8');
            });

        Sale::query()
            ->published()
            ->get(['id', 'slug', 'updated_at'])
            ->each(function (Sale $sale) use (&$entries): void {
                $entries[] = $this->entry(route('sales.show', $sale), $sale->updated_at?->toAtomString(), 'hourly', '0.7');
            });

        // De blog uit het CMS van Eazyonline: de index en de nieuwste berichten.
        $blog = app(BlogClient::class);

        if ($blog->configured()) {
            $entries[] = $this->entry(route('blog.index'), null, 'weekly', '0.5');

            foreach ($blog->posts(1, 100)['posts'] as $post) {
                $entries[] = $this->entry(route('blog.show', $post['slug']), ($post['updated_at'] ?? $post['published_at'])?->toAtomString(), 'monthly', '0.5');
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $entries)."\n"
            .'</urlset>'."\n";
    }

    private function entry(string $url, ?string $modified, string $frequency, string $priority): string
    {
        $parts = ['<loc>'.e($url).'</loc>'];

        if ($modified !== null) {
            $parts[] = '<lastmod>'.$modified.'</lastmod>';
        }

        $parts[] = '<changefreq>'.$frequency.'</changefreq>';
        $parts[] = '<priority>'.$priority.'</priority>';

        return '  <url>'.implode('', $parts).'</url>';
    }
}
