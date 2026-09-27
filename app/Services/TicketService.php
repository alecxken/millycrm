<?php

namespace App\Services;

use App\Enums\Direction;
use App\Enums\InteractionType;
use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\ServiceTicket;
use App\Models\TicketNote;

class TicketService
{
    public function open(array $data): ServiceTicket
    {
        $priority = $data['priority'] instanceof Priority ? $data['priority'] : Priority::from($data['priority'] ?? 'normal');

        $ticket = ServiceTicket::create([
            ...$data,
            'priority' => $priority,
            'status' => TicketStatus::Open,
            'sla_due_at' => now()->addHours($priority->slaHours()),
        ]);

        $ticket->customer->interactions()->create([
            'user_id' => auth()->id(),
            'type' => InteractionType::Note,
            'direction' => Direction::Inbound,
            'subject' => "Ticket {$ticket->reference} opened: {$ticket->subject}",
            'body' => $ticket->description,
            'occurred_at' => now(),
            'related_type' => ServiceTicket::class,
            'related_id' => $ticket->id,
        ]);

        return $ticket;
    }

    public function addNote(ServiceTicket $ticket, string $body, bool $internal = true): TicketNote
    {
        if ($ticket->status === TicketStatus::Open) {
            $ticket->update(['status' => TicketStatus::InProgress]);
        }

        return $ticket->notes()->create([
            'user_id' => auth()->id(),
            'body' => $body,
            'is_internal' => $internal,
        ]);
    }

    public function resolve(ServiceTicket $ticket, string $resolution): ServiceTicket
    {
        $ticket->update([
            'status' => TicketStatus::Resolved,
            'resolution' => $resolution,
            'resolved_at' => now(),
        ]);

        $ticket->customer->interactions()->create([
            'user_id' => auth()->id(),
            'type' => InteractionType::Note,
            'direction' => Direction::Outbound,
            'subject' => "Ticket {$ticket->reference} resolved",
            'body' => $resolution,
            'occurred_at' => now(),
            'related_type' => ServiceTicket::class,
            'related_id' => $ticket->id,
        ]);

        return $ticket;
    }
}
