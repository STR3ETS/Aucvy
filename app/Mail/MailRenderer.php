<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Lot;
use App\Models\MailTemplateVersion;
use App\Support\Money\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Zet een templateversie met velden om in de vaste mailopbouw: donkere
 * kop met afzenderregel, één boodschap, bouwstenen, één hoofdknop, vaste
 * voet. Ook een tekstversie. Kavelblokken halen de kavels op het moment
 * van renderen op.
 */
final class MailRenderer
{
    public const BLOCKS = ['heading', 'text', 'image', 'button', 'status', 'ivy_tip', 'divider', 'lots', 'amount', 'lines', 'steps', 'code', 'footnote'];

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Lot>|null  $lots  kavels voor de kavelblokken uit de context
     */
    public function render(MailTemplateVersion $version, array $fields, ?Collection $lots = null, ?string $unsubscribeUrl = null): RenderedMail
    {
        $template = $version->template;
        $blocks = collect($version->blocks)->map(fn (array $block) => $this->resolveBlock($block, $fields, $lots))->all();
        $subject = MailFields::resolve($version->subject, $fields);
        $preheader = MailFields::resolve((string) $version->preheader, $fields);

        $html = view('mail.template', [
            'subject' => $subject,
            'preheader' => $preheader,
            'sender' => $version->sender,
            'blocks' => $blocks,
            'stream' => $template->stream,
            'unsubscribeUrl' => $unsubscribeUrl,
        ])->render();

        return new RenderedMail($subject, $preheader, $html, $this->text($blocks, $subject, $unsubscribeUrl));
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Lot>|null  $lots
     * @return array<string, mixed>
     */
    private function resolveBlock(array $block, array $fields, ?Collection $lots): array
    {
        $type = (string) ($block['type'] ?? 'text');

        if ($type === 'lots') {
            $limit = max(1, (int) ($block['limit'] ?? 3));
            $selection = ($block['source'] ?? 'context') === 'latest' || $lots === null || $lots->isEmpty()
                ? Lot::query()->live()->with(['auction', 'media', 'category', 'location'])->latest('published_at')->limit($limit)->get()
                : $lots->take($limit);

            return ['type' => 'lots', 'lots' => $selection->map(fn (Lot $lot) => [
                'title' => $lot->title,
                'meta' => trim(($lot->category?->name ?? '').($lot->location ? ' · '.$lot->location->city : '')),
                'price' => $lot->auction ? Money::format($lot->currentPriceCents()) : '',
                'price_label' => $lot->auction?->isClosed() ? 'eindprijs' : (($lot->auction?->bid_count ?? 0) > 0 ? 'huidig bod' : 'startbod'),
                'ends' => $lot->auction && ! $lot->auction->isClosed() ? $lot->auction->ends_at->timezone(config('aucvy.display_timezone'))->isoFormat('D MMM, HH:mm') : null,
                'url' => route('lots.show', $lot),
                'image' => $lot->media->first()?->path,
            ])->values()->all()];
        }

        $resolved = ['type' => $type];

        foreach ($block as $key => $value) {
            if ($key === 'type') {
                continue;
            }

            $resolved[$key] = match (true) {
                is_string($value) => MailFields::resolve($value, $fields),
                is_array($value) => array_map(fn ($item) => is_array($item)
                    ? array_map(fn ($part) => is_string($part) ? MailFields::resolve($part, $fields) : $part, $item)
                    : (is_string($item) ? MailFields::resolve($item, $fields) : $item), $value),
                default => $value,
            };
        }

        return $resolved;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    private function text(array $blocks, string $subject, ?string $unsubscribeUrl): string
    {
        $lines = [Str::upper($subject), ''];

        foreach ($blocks as $block) {
            $lines[] = match ($block['type']) {
                'heading' => Str::upper((string) $block['text']),
                'text', 'footnote' => (string) $block['text'],
                'status', 'ivy_tip' => trim((string) ($block['title'] ?? '')).': '.(string) ($block['text'] ?? ''),
                'button' => (string) $block['label'].': '.(string) $block['url'],
                'amount' => (string) $block['label'].' '.(string) $block['value'].(($block['note'] ?? '') !== '' ? ' ('.$block['note'].')' : ''),
                'lines' => implode("\n", array_map(fn ($item) => '- '.$item['label'].': '.$item['value'], (array) $block['items'])),
                'steps' => implode("\n", array_map(fn ($step, $index) => ($index + 1).'. '.$step, (array) $block['items'], array_keys((array) $block['items']))),
                'code' => 'Je code: '.(string) $block['value'],
                'lots' => implode("\n", array_map(fn ($lot) => '- '.$lot['title'].' · '.$lot['price'].' '.$lot['price_label'].' · '.$lot['url'], (array) $block['lots'])),
                'divider' => '---',
                default => '',
            };
            $lines[] = '';
        }

        $lines[] = 'Aucvy · Voorwaarden · Privacy · Contact';

        if ($unsubscribeUrl !== null) {
            $lines[] = 'Afmelden: '.$unsubscribeUrl;
        }

        return trim(implode("\n", $lines));
    }
}
