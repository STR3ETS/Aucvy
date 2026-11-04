<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\Campaigns;
use App\Models\MailCampaign;
use Illuminate\Console\Command;

final class SendMailCampaigns extends Command
{
    protected $signature = 'aucvy:send-campaigns';

    protected $description = 'Verstuurt de ingeplande campagnes uit de Mail Builder';

    public function handle(Campaigns $campaigns): int
    {
        $due = MailCampaign::query()->with('version.template')->where('status', MailCampaign::SCHEDULED)->where('scheduled_at', '<=', now())->get();

        foreach ($due as $campaign) {
            $result = $campaigns->send($campaign);
            $this->info("{$campaign->name}: {$result['sent']} verstuurd, {$result['skipped']} overgeslagen.");
        }

        return self::SUCCESS;
    }
}
