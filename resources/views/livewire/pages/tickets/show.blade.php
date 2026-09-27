<?php

use App\Enums\Priority;
use App\Enums\TicketStatus;
use App\Models\ServiceTicket;
use App\Services\TicketService;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public ServiceTicket $ticket;

    public string $note = '';

    public bool $internal = true;

    public string $resolution = '';

    public function mount(ServiceTicket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticket = $ticket;
    }

    public function rendering($view): void
    {
        $view->title($this->ticket->reference.' · '.$this->ticket->subject);
    }

    public function addNote(TicketService $tickets): void
    {
        $this->authorize('update', $this->ticket);
        $this->validate(['note' => 'required|string|max:5000']);
        $tickets->addNote($this->ticket, $this->note, $this->internal);
        $this->note = '';
        $this->ticket->refresh();
        $this->dispatch('toast', message: $this->internal ? 'Internal note added.' : 'Reply logged.');
    }

    public function setPriority(string $priority): void
    {
        $this->authorize('update', $this->ticket);
        $p = Priority::from($priority);
        $this->ticket->update(['priority' => $p, 'sla_due_at' => $this->ticket->created_at->copy()->addHours($p->slaHours())]);
        $this->dispatch('toast', message: "Priority set to {$p->label()} — SLA recalculated.");
    }

    public function resolve(TicketService $tickets): void
    {
        $this->authorize('update', $this->ticket);
        $this->validate(['resolution' => 'required|string|min:10|max:5000'], attributes: ['resolution' => 'resolution']);
        $tickets->resolve($this->ticket, $this->resolution);
        $this->ticket->refresh();
        $this->dispatch('toast', message: 'Ticket resolved. The customer timeline has been updated.');
    }

    public function reopen(): void
    {
        $this->authorize('update', $this->ticket);
        $this->ticket->update(['status' => TicketStatus::InProgress, 'resolved_at' => null]);
        $this->dispatch('toast', message: 'Ticket reopened.', type: 'warning');
    }

    public function close(): void
    {
        $this->authorize('update', $this->ticket);
        $this->ticket->update(['status' => TicketStatus::Closed]);
        $this->dispatch('toast', message: 'Ticket closed.');
    }

    public function with(): array
    {
        $this->ticket->load(['customer:id,first_name,last_name,company_name,type,phone,whatsapp,email,lifecycle_stage', 'booking:id,reference,destination,start_date,end_date', 'assignee:id,name,avatar_color', 'notes' => fn ($q) => $q->oldest()->with('user:id,name,avatar_color')]);

        return [
            'previous' => ServiceTicket::where('customer_id', $this->ticket->customer_id)->whereKeyNot($this->ticket->id)->latest()->limit(5)->get(['id', 'reference', 'subject', 'status', 'created_at']),
        ];
    }
}; ?>

@php($t = $ticket)
<div>
    <nav class="mb-4 text-sm"><a href="{{ route('tickets.index') }}" wire:navigate class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"><x-hicon name="arrow-left" class="size-4" /> Tickets</a></nav>

    <x-page-header :title="$t->subject" :subtitle="$t->reference.' · opened '.fdate($t->created_at, true)">
        <x-slot:actions>
            <x-badge :enum="$t->category" /><x-badge :enum="$t->status" />@include('partials.sla', ['ticket' => $t])
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="What happened">
                <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{ $t->description ?: 'No description provided.' }}</p>
            </x-card>

            <x-card title="Notes & updates" subtitle="Internal notes are only visible to staff" :padding="false">
                <ol class="space-y-4 p-4">
                    @forelse ($t->notes as $n)
                        <li class="flex gap-3">
                            @if ($n->user)<x-avatar :user="$n->user" size="sm" />@endif
                            <div @class(['min-w-0 flex-1 rounded-xl p-3 text-sm', 'bg-amber-50 dark:bg-amber-400/10' => $n->is_internal, 'bg-slate-50 dark:bg-slate-800/60' => ! $n->is_internal])>
                                <p class="mb-1 flex flex-wrap items-center gap-2 text-xs text-slate-500"><span class="font-semibold text-slate-800 dark:text-slate-200">{{ $n->user?->name ?? 'System' }}</span> {{ fdate($n->created_at, true) }} @if ($n->is_internal)<x-badge color="amber" icon="lock-closed" label="Internal" size="xs" />@else<x-badge color="sky" icon="chat-bubble-left" label="Customer reply" size="xs" />@endif</p>
                                <p class="whitespace-pre-line">{{ $n->body }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">No notes yet. Log your first step so the team knows what's been done.</li>
                    @endforelse
                </ol>
                @can('update', $t)
                    @if ($t->isOpen())
                        <form wire:submit="addNote" class="border-t border-slate-100 p-4 dark:border-slate-800">
                            <label for="note" class="sr-only">Add a note</label>
                            <textarea id="note" wire:model="note" rows="3" class="form-input" placeholder="What did you do? What's next?"></textarea>
                            @error('note')<p class="mt-1 text-xs text-rose-700">{{ $message }}</p>@enderror
                            <div class="mt-2 flex items-center justify-between">
                                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="internal" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600"> Internal note</label>
                                <x-button type="submit" size="sm" icon="paper-airplane" loading="addNote">Add note</x-button>
                            </div>
                        </form>
                    @endif
                @endcan
            </x-card>

            @if ($t->isOpen())
                @can('update', $t)
                    <x-card title="Resolve this ticket" subtitle="Capture what fixed it — it becomes part of the customer's history">
                        <form wire:submit="resolve" class="space-y-3">
                            <label for="resolution" class="sr-only">Resolution</label>
                            <textarea id="resolution" wire:model="resolution" rows="3" class="form-input" placeholder="e.g. Emergency travel document issued; flight rebooked at no extra cost."></textarea>
                            @error('resolution')<p class="text-xs text-rose-700">{{ $message }}</p>@enderror
                            <x-button type="submit" icon="check-circle" loading="resolve">Mark resolved</x-button>
                        </form>
                    </x-card>
                @endcan
            @else
                <x-card title="Resolution">
                    <p class="text-sm">{{ $t->resolution }}</p>
                    <p class="mt-2 text-xs text-slate-500">Resolved {{ fdate($t->resolved_at, true) }} · {{ $t->isBreached() ? 'outside' : 'within' }} SLA</p>
                    @can('update', $t)
                        <div class="mt-3 flex gap-2">
                            @if ($t->status === \App\Enums\TicketStatus::Resolved)<x-button size="sm" variant="secondary" wire:click="close">Close ticket</x-button>@endif
                            <x-button size="sm" variant="ghost" wire:click="reopen">Reopen</x-button>
                        </div>
                    @endcan
                </x-card>
            @endif
        </div>

        <aside class="space-y-6">
            <x-card title="Customer">
                <a href="{{ route('customers.show', $t->customer) }}" wire:navigate class="font-semibold hover:text-brand-700">{{ $t->customer->display_name }}</a>
                <div class="mt-1"><x-badge :enum="$t->customer->lifecycle_stage" size="xs" /></div>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    @if ($t->customer->phone)<a href="tel:{{ $t->customer->phone }}" class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1 font-medium dark:bg-slate-800"><x-hicon name="phone" class="size-4" />Call</a>@endif
                    @if ($t->customer->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $t->customer->whatsapp) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 font-medium text-emerald-800 dark:bg-emerald-400/10 dark:text-emerald-300"><x-hicon name="chat-bubble-left-right" class="size-4" />WhatsApp</a>@endif
                </div>
                @if ($t->booking)<p class="mt-4 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">Booking <strong>{{ $t->booking->reference }}</strong> · {{ $t->booking->destination }}<br><span class="text-xs text-slate-500">{{ fdate($t->booking->start_date) }} – {{ fdate($t->booking->end_date) }}</span></p>@endif
            </x-card>
            <x-card title="Handling">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between"><dt class="text-slate-500">Owner</dt><dd class="flex items-center gap-2 font-medium">@if ($t->assignee)<x-avatar :user="$t->assignee" size="xs" />{{ $t->assignee->name }}@else Unassigned @endif</dd></div>
                    <div class="flex items-center justify-between"><dt class="text-slate-500">SLA due</dt><dd class="font-medium">{{ fdate($t->sla_due_at, true) }}</dd></div>
                    <div>
                        <dt class="mb-1 text-slate-500">Priority</dt>
                        <dd>
                            @can('update', $t)
                                <select class="form-input" x-on:change="$wire.setPriority($event.target.value)" aria-label="Priority">@foreach (Priority::cases() as $p)<option value="{{ $p->value }}" @selected($p === $t->priority)>{{ $p->label() }} ({{ $p->slaHours() }}h SLA)</option>@endforeach</select>
                            @else<x-badge :enum="$t->priority" />@endcan
                        </dd>
                    </div>
                </dl>
            </x-card>
            @if ($previous->isNotEmpty())
                <x-card title="Earlier tickets" :padding="false">
                    @foreach ($previous as $p)
                        <a href="{{ route('tickets.show', $p) }}" wire:navigate class="flex items-center gap-2 border-b border-slate-100 px-4 py-2.5 text-sm last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"><span class="min-w-0 flex-1 truncate">{{ $p->subject }}</span><x-badge :enum="$p->status" size="xs" /></a>
                    @endforeach
                </x-card>
            @endif
        </aside>
    </div>
</div>
