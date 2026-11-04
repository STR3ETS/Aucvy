<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CustomerTask;
use App\Models\SupportTicket;
use Illuminate\Console\Command;

/**
 * Bewaakt de reactietijd van tickets: te laat wordt een taak voor Flow in
 * "Wacht op jou". Eén taak per ticket.
 */
final class CheckTicketSla extends Command
{
    protected $signature = 'aucvy:check-ticket-sla';

    protected $description = 'Zet tickets die over de reactietijd heen zijn in Wacht op jou';

    public function handle(): int
    {
        $late = SupportTicket::query()->late()->whereNull('sla_task_at')->get();

        foreach ($late as $ticket) {
            CustomerTask::ensure("ticket-late:{$ticket->id}", [
                'user_id' => $ticket->user_id,
                'title' => "Ticket {$ticket->number} wacht al ".$ticket->created_at->diffForHumans(null, true).' op een eerste reactie',
                'agent' => 'flow',
                'url' => route('admin.service.show', $ticket),
                'due_at' => now(),
            ]);

            $ticket->forceFill(['sla_task_at' => now()])->save();
        }

        $this->info("{$late->count()} tickets over de reactietijd.");

        return self::SUCCESS;
    }
}
