<?php

use App\Enums\Priority;
use App\Enums\Role;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\ServiceTicket;
use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Tickets')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $filter = 'open';

    #[Url]
    public string $category = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function assign(int $ticketId, int $userId): void
    {
        $ticket = ServiceTicket::findOrFail($ticketId);
        $this->authorize('update', $ticket);
        $ticket->update(['assigned_to' => $userId]);
        $this->dispatch('toast', message: "{$ticket->reference} assigned.");
    }

    public function with(): array
    {
        $query = ServiceTicket::query()
            ->with(['customer:id,first_name,last_name,company_name,type', 'assignee:id,name,avatar_color', 'booking:id,reference,destination'])
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('subject', 'like', "%{$this->search}%")->orWhere('reference', 'like', "%{$this->search}%")->orWhereHas('customer', fn ($c) => $c->search($this->search))));

        match ($this->filter) {
            'mine' => $query->unresolved()->where('assigned_to', auth()->id())->orderBy('sla_due_at'),
            'breached' => $query->unresolved()->where('sla_due_at', '<', now())->orderBy('sla_due_at'),
            'resolved' => $query->whereIn('status', [TicketStatus::Resolved, TicketStatus::Closed])->latest('resolved_at'),
            default => $query->unresolved()->orderBy('sla_due_at'),
        };

        $open = ServiceTicket::unresolved();

        return [
            'tickets' => $query->paginate(15),
            'counts' => [
                'open' => (clone $open)->count(),
                'mine' => (clone $open)->where('assigned_to', auth()->id())->count(),
                'breached' => (clone $open)->where('sla_due_at', '<', now())->count(),
                'resolved' => ServiceTicket::whereIn('status', [TicketStatus::Resolved, TicketStatus::Closed])->count(),
            ],
            'staff' => User::role([Role::Support->value, Role::Manager->value])->orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<div>
    <x-page-header title="Service tickets" subtitle="Sorted by SLA deadline — the most urgent customer is always at the top.">
        <x-slot:actions><x-button variant="secondary" icon="users" :href="route('customers.index')" wire:navigate>Open a ticket from a customer</x-button></x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="inline-flex max-w-full overflow-x-auto rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800" role="tablist">
            @foreach (['open' => 'All open', 'mine' => 'Assigned to me', 'breached' => 'SLA breached', 'resolved' => 'Resolved'] as $key => $label)
                <button type="button" role="tab" wire:click="$set('filter', '{{ $key }}')" aria-selected="{{ $filter === $key ? 'true' : 'false' }}" @class(['inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 font-medium', 'bg-white shadow-sm dark:bg-slate-950' => $filter === $key, 'text-slate-600 dark:text-slate-400' => $filter !== $key])>
                    {{ $label }} <span @class(['rounded-full px-1.5 text-xs', 'bg-rose-100 text-rose-700 dark:bg-rose-400/20 dark:text-rose-300' => $key === 'breached' && $counts[$key], 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => ! ($key === 'breached' && $counts[$key])])>{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </div>
        <div class="flex gap-3">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Subject, TK- reference, customer" class="form-input sm:w-64" aria-label="Search tickets">
            <select wire:model.live="category" class="form-input w-44" aria-label="Category"><option value="">All categories</option>@foreach (TicketCategory::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </div>
    </div>

    <div class="card overflow-hidden" wire:loading.class="opacity-60" wire:poll.60s.visible>
        @if ($tickets->isEmpty())
            <x-empty-state icon="face-smile" :title="$filter === 'breached' ? 'No SLA breaches' : 'Inbox zero'" description="Nothing needs attention here right now." />
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($tickets as $t)
                    <li wire:key="t-{{ $t->id }}" class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center">
                        <div class="flex min-w-0 flex-1 items-start gap-3">
                            <x-badge :enum="$t->category" class="hidden sm:inline-flex" />
                            <div class="min-w-0">
                                <a href="{{ route('tickets.show', $t) }}" wire:navigate class="font-semibold text-slate-900 hover:text-brand-700 dark:text-white dark:hover:text-brand-400">{{ $t->subject }}</a>
                                <p class="text-xs text-slate-500">{{ $t->reference }} · {{ $t->customer->display_name }}{{ $t->booking ? ' · '.$t->booking->destination : '' }} · opened {{ $t->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <x-badge :enum="$t->priority" />
                            <x-badge :enum="$t->status" />
                            @include('partials.sla', ['ticket' => $t])
                            @can('update', $t)
                                <select class="form-input w-36 py-1 text-xs" aria-label="Assign {{ $t->reference }}" x-on:change="$wire.assign({{ $t->id }}, $event.target.value)">
                                    <option value="" disabled @selected(! $t->assigned_to)>Unassigned</option>
                                    @foreach ($staff as $s)<option value="{{ $s->id }}" @selected($s->id === $t->assigned_to)>{{ $s->name }}</option>@endforeach
                                </select>
                            @endcan
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $tickets->links() }}</div>
        @endif
    </div>
</div>
