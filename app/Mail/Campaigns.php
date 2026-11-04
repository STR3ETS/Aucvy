<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\UserType;
use App\Models\Lot;
use App\Models\MailCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Campagnes uit de Mail Builder: een segment, een moment, en bij het
 * versturen verse kavels en een toets op toestemming per ontvanger.
 */
final class Campaigns
{
    public function __construct(private readonly MailSender $sender) {}

    /**
     * @return Builder<User>
     */
    public function audience(MailCampaign $campaign): Builder
    {
        $segment = (array) $campaign->segment;

        return User::query()->whereNotNull('email_verified_at')->where('is_staff', false)
            ->when(($segment['audience'] ?? 'all') === 'buyers', fn ($query) => $query->where('type', UserType::Private))
            ->when(($segment['audience'] ?? 'all') === 'sellers', fn ($query) => $query->where('type', UserType::Business))
            ->when(($segment['audience'] ?? 'all') === 'category' && ! empty($segment['category_id']), fn ($query) => $query->where(fn ($inner) => $inner
                ->whereHas('watchlistItems', fn ($watch) => $watch->whereHas('lot', fn ($lot) => $lot->where('category_id', (int) $segment['category_id'])))
                ->orWhereHas('lots', fn ($lot) => $lot->where('category_id', (int) $segment['category_id']))));
    }

    /**
     * @return array{sent: int, skipped: int}
     */
    public function send(MailCampaign $campaign): array
    {
        $campaign->forceFill(['status' => MailCampaign::SENDING])->save();
        $sent = 0;
        $skipped = 0;
        $segment = (array) $campaign->segment;

        $lots = Lot::query()->live()->with(['auction', 'media', 'category', 'location'])
            ->when(! empty($segment['category_id']), fn ($query) => $query->where('category_id', (int) $segment['category_id']))
            ->when(! empty($segment['lot_id']), fn ($query) => $query->orWhere('id', (int) $segment['lot_id']))
            ->latest('published_at')->limit(6)->get();

        $context = ['lots' => $lots, 'categorie' => (string) ($segment['label'] ?? 'nieuwe kavels'), 'titel' => (string) ($segment['title'] ?? $campaign->name), 'intro' => (string) ($segment['intro'] ?? ''), 'link' => (string) ($segment['link'] ?? route('lots.index')), 'moment' => (string) ($segment['moment'] ?? ''), 'adres' => (string) ($segment['address'] ?? ''), 'aantal' => (string) $lots->count()];

        if (! empty($segment['lot_id'])) {
            $context['lot'] = $lots->firstWhere('id', (int) $segment['lot_id']) ?? Lot::query()->find((int) $segment['lot_id']);
        }

        $this->audience($campaign)->chunkById(200, function ($users) use ($campaign, $context, &$sent, &$skipped): void {
            foreach ($users as $user) {
                $outcome = $this->sender->send($user, $campaign->version->template->key, $context, null, $campaign, $campaign->version);
                $outcome === null ? $sent++ : $skipped++;
            }
        });

        $campaign->forceFill(['status' => MailCampaign::SENT, 'sent_count' => $sent, 'skipped_count' => $skipped, 'sent_at' => now()])->save();

        return compact('sent', 'skipped');
    }
}
