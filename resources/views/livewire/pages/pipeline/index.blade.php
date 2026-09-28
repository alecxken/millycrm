<?php

use App\Enums\CustomerSource;
use App\Enums\EnquiryStatus;
use App\Enums\Role;
use App\Enums\TravelStyle;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\PipelineService;
use App\Services\QuoteService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Pipeline')] class extends Component
{
    #[Url]
    public string $view = 'board';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $consultant = '';

    #[Url(as: 'enquiry')]
    public ?int $selectedId = null;

    public ?string $panel = null;

    public ?int $losingId = null;

    public string $lostReason = '';

    public array $form = [];

    public string $customerSearch = '';

    public function mount(): void
    {
        $this->resetForm();
        if (request()->boolean('new')) {
            $this->panel = 'new-enquiry';
        }
        if ($this->selectedId) {
            $this->panel = 'enquiry';
        }
    }

    public function resetForm(): void
    {
        $this->form = [
            'customer_id' => null, 'new_customer' => false, 'first_name' => '', 'last_name' => '', 'phone' => '',
            'destination' => '', 'departure_date' => now()->addMonth()->format('Y-m-d'), 'return_date' => now()->addMonth()->addDays(5)->format('Y-m-d'),
            'travellers_adults' => 2, 'travellers_children' => 0, 'budget' => '', 'trip_type' => '', 'channel' => 'whatsapp',
        ];
        $this->customerSearch = '';
        $this->resetValidation();
    }

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->panel = 'enquiry';
    }

    public function updatedPanel($value): void
    {
        if ($value === null) {
            $this->selectedId = null;
            $this->losingId = null;
        }
    }

    /** Called by the Kanban drag-and-drop. */
    public function moveCard(int $id, string $status, int $position = 0): void
    {
        $pipeline = app(PipelineService::class);
        $enquiry = Enquiry::findOrFail($id);
        $this->authorize('update', $enquiry);
        $target = EnquiryStatus::from($status);

        if ($target === EnquiryStatus::Lost) {
            // Losing requires a reason: ask first, move after.
            $this->losingId = $id;
            $this->lostReason = '';
            $this->panel = 'lost';

            return;
        }

        if ($target === EnquiryStatus::Won && ! $enquiry->quotes()->exists()) {
            $this->dispatch('toast', message: 'Build and accept a quote first — winning creates the booking.', type: 'warning');

            return;
        }

        if ($target === EnquiryStatus::Won) {
            $this->selectedId = $id;
            $this->panel = 'enquiry';
            $this->dispatch('toast', message: 'Convert the accepted quote to a booking to mark this as won.', type: 'warning');

            return;
        }

        $from = $enquiry->status;
        $pipeline->move($enquiry, $target, position: $position);
        $this->dispatch('toast', message: "{$enquiry->reference} moved to {$target->label()}.", undo: ['id' => $this->getId(), 'method' => 'undoMove', 'args' => [$id, $from->value]]);
    }

    public function undoMove(int $id, string $status, PipelineService $pipeline): void
    {
        $enquiry = Enquiry::findOrFail($id);
        $this->authorize('update', $enquiry);
        $pipeline->move($enquiry, EnquiryStatus::from($status));
    }

    public function confirmLost(PipelineService $pipeline): void
    {
        $this->validate(['lostReason' => 'required|string|min:3|max:255'], attributes: ['lostReason' => 'lost reason']);
        $enquiry = Enquiry::findOrFail($this->losingId);
        $this->authorize('update', $enquiry);

        $pipeline->move($enquiry, EnquiryStatus::Lost, $this->lostReason);
        $enquiry->tasks()->whereNull('completed_at')->update(['completed_at' => now()]);

        $this->panel = null;
        $this->losingId = null;
        $this->dispatch('toast', message: "{$enquiry->reference} marked as lost — reason recorded for reporting.");
    }

    public function setStatus(string $status): void
    {
        $enquiry = Enquiry::findOrFail($this->selectedId);
        $this->moveCard($enquiry->id, $status, $enquiry->position);
    }

    public function reassign(int $userId): void
    {
        $enquiry = Enquiry::findOrFail($this->selectedId);
        $this->authorize('reassign', Enquiry::class);
        $enquiry->update(['assigned_to' => $userId]);
        $this->dispatch('toast', message: 'Enquiry reassigned.');
    }

    public function startQuote(QuoteService $quotes): void
    {
        $enquiry = Enquiry::findOrFail($this->selectedId);
        $this->authorize('update', $enquiry);
        $quote = $quotes->createDraft($enquiry);
        $this->redirectRoute('quotes.edit', $quote, navigate: true);
    }

    public function pickCustomer(int $id): void
    {
        $this->form['customer_id'] = $id;
        $this->form['new_customer'] = false;
        $customer = Customer::with('preference')->find($id);
        if ($customer && blank($this->form['destination'])) {
            $this->form['destination'] = $customer->preference?->preferred_destinations[0] ?? '';
            $this->form['trip_type'] = $customer->preference?->travel_style?->value ?? '';
        }
    }

    public function create(CustomerService $customers): void
    {
        $this->authorize('create', Enquiry::class);
        $rules = [
            'form.destination' => 'required|string|max:120',
            'form.departure_date' => 'nullable|date|after_or_equal:today',
            'form.return_date' => 'nullable|date|after_or_equal:form.departure_date',
            'form.travellers_adults' => 'required|integer|min:1|max:200',
            'form.travellers_children' => 'required|integer|min:0|max:200',
            'form.budget' => 'nullable|numeric|min:0',
            'form.trip_type' => ['nullable', Rule::enum(TravelStyle::class)],
            'form.channel' => ['required', Rule::enum(CustomerSource::class)],
        ];
        $rules += $this->form['new_customer']
            ? ['form.first_name' => 'required|string|max:80', 'form.last_name' => 'required|string|max:80', 'form.phone' => 'required|string|max:40']
            : ['form.customer_id' => 'required|exists:customers,id'];

        $data = $this->validate($rules, attributes: ['form.customer_id' => 'customer', 'form.destination' => 'destination', 'form.first_name' => 'first name', 'form.last_name' => 'last name', 'form.phone' => 'phone'])['form'];

        $customer = $this->form['new_customer']
            ? $customers->create(['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'phone' => $data['phone'], 'whatsapp' => $data['phone'], 'source' => $data['channel'], 'type' => 'individual', 'country' => 'Kenya', 'preferred_contact_channel' => 'whatsapp', 'marketing_consent' => false])
            : Customer::findOrFail($data['customer_id']);

        $enquiry = Enquiry::create([
            'customer_id' => $customer->id,
            'destination' => $data['destination'],
            'departure_date' => $data['departure_date'] ?: null,
            'return_date' => $data['return_date'] ?: null,
            'travellers_adults' => $data['travellers_adults'],
            'travellers_children' => $data['travellers_children'],
            'budget' => $data['budget'] ?: null,
            'trip_type' => $data['trip_type'] ?: null,
            'channel' => $data['channel'],
            'assigned_to' => auth()->user()->hasRole(Role::Consultant->value) ? auth()->id() : ($customer->assigned_to ?? auth()->id()),
            'expected_value' => $data['budget'] ?: 0,
            'probability' => EnquiryStatus::New->probability(),
            'stage_changed_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->resetForm();
        $this->selectedId = $enquiry->id;
        $this->panel = 'enquiry';
        $this->dispatch('toast', message: "Enquiry {$enquiry->reference} captured. Next: build a quote.");
    }

    public function with(): array
    {
        $user = auth()->user();
        $base = Enquiry::query()->visibleTo($user)
            ->with(['customer:id,first_name,last_name,company_name,type', 'consultant:id,name,avatar_color'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('destination', 'like', "%{$this->search}%")->orWhere('reference', 'like', "%{$this->search}%")->orWhereHas('customer', fn ($c) => $c->search($this->search))))
            ->when($this->consultant, fn ($q) => $q->where('assigned_to', $this->consultant))
            // Closed deals only for the last 30 days, so the board stays about "now".
            ->where(fn ($q) => $q->open()->orWhere('stage_changed_at', '>=', now()->subDays(30)));

        $enquiries = $base->orderBy('position')->orderByDesc('last_activity_at')->get();
        $columns = collect(EnquiryStatus::board())->mapWithKeys(fn ($s) => [$s->value => $enquiries->where('status', $s)->values()]);

        $selected = $this->selectedId
            ? Enquiry::visibleTo($user)->with(['customer.preference', 'consultant:id,name,avatar_color', 'quotes' => fn ($q) => $q->latest()->withCount('items')->with('booking:id,quote_id,reference'), 'interactions' => fn ($q) => $q->latest('occurred_at')->limit(5), 'tasks' => fn ($q) => $q->whereNull('completed_at')])->find($this->selectedId)
            : null;

        $customerResults = strlen($this->customerSearch) >= 2
            ? Customer::visibleTo($user)->search($this->customerSearch)->limit(6)->get(['id', 'first_name', 'last_name', 'company_name', 'type', 'phone'])
            : collect();

        return [
            'columns' => $columns,
            'list' => $enquiries->sortByDesc('created_at'),
            'selected' => $selected,
            'consultants' => User::role(Role::Consultant->value)->orderBy('name')->get(['id', 'name', 'avatar_color']),
            'customerResults' => $customerResults,
            'pickedCustomer' => $this->form['customer_id'] ? Customer::find($this->form['customer_id']) : null,
            'totals' => $columns->map(fn ($c) => ['count' => $c->count(), 'value' => $c->sum('expected_value')]),
        ];
    }
}; ?>

<div>
    <x-page-header title="Sales pipeline" subtitle="Drag a card along when the conversation moves on. Amber means someone has gone quiet, so give them a nudge.">
        <x-slot:actions>
            <div class="inline-flex max-w-full gap-1 overflow-x-auto rounded-full border border-[var(--hairline)] bg-white p-1 text-[12.5px] shadow-[var(--shadow-panel)] dark:bg-slate-900" role="tablist" aria-label="Layout">
                @foreach (['board' => ['Board', 'view-columns'], 'list' => ['List', 'list-bullet']] as $key => [$label, $icon])
                    <button type="button" role="tab" wire:click="$set('view', '{{ $key }}')" aria-selected="{{ $view === $key ? 'true' : 'false' }}"
                            @class(['inline-flex items-center gap-1.5 rounded-full px-4 py-1.5 font-bold', 'bg-brand-800 text-white shadow-[0_4px_12px_color-mix(in_srgb,var(--brand)_25%,transparent)]' => $view === $key, 'text-slate-500 hover:text-brand-800 dark:text-slate-400 dark:hover:text-white' => $view !== $key])>
                        <x-hicon :name="$icon" class="size-4" />{{ $label }}
                    </button>
                @endforeach
            </div>
            @can('pipeline.manage')<x-button icon="plus" wire:click="$set('panel', 'new-enquiry')">New enquiry</x-button>@endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <div class="relative flex-1 sm:max-w-sm">
            <x-hicon name="magnifying-glass" class="pointer-events-none absolute top-2.5 left-3 size-5 text-slate-400" />
            <label for="pl-search" class="sr-only">Search pipeline</label>
            <input id="pl-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Destination, customer or ENQ- reference" class="form-input pl-10">
        </div>
        @if (auth()->user()->can('pipeline.view_all'))
            <label for="pl-consultant" class="sr-only">Consultant</label>
            <select id="pl-consultant" wire:model.live="consultant" class="form-input sm:w-56">
                <option value="">Whole team</option>
                @foreach ($consultants as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        @endif
    </div>

    @if ($view === 'board')
        <div class="-mx-4 overflow-x-auto px-4 pb-4 sm:-mx-6 sm:px-6 lg:mx-0 lg:px-0">
            <div class="flex min-w-max gap-4">
                @foreach (\App\Enums\EnquiryStatus::board() as $status)
                    <section class="flex w-72 shrink-0 flex-col rounded-2xl bg-slate-100/80 dark:bg-slate-900/60" aria-label="{{ $status->label() }}">
                        <header class="flex items-center justify-between px-3 pt-3 pb-2">
                            <div class="flex items-center gap-2">
                                <x-badge :enum="$status" />
                                <span class="text-xs font-semibold text-slate-500">{{ $totals[$status->value]['count'] }}</span>
                            </div>
                            <span class="text-xs font-medium text-slate-500 tabular-nums">{{ money($totals[$status->value]['value'], compact: true) }}</span>
                        </header>
                        <div x-data="kanbanColumn('{{ $status->value }}')" data-status="{{ $status->value }}" class="flex min-h-40 flex-1 flex-col gap-2 px-2 pb-3">
                            @foreach ($columns[$status->value] as $e)
                                @php($stale = $e->isStale())
                                <article wire:key="card-{{ $e->id }}" data-id="{{ $e->id }}" wire:click="select({{ $e->id }})" tabindex="0" x-on:keydown.enter="$wire.select({{ $e->id }})"
                                         @class(['cursor-grab rounded-xl border bg-white p-3 shadow-sm transition hover:shadow-md active:cursor-grabbing dark:bg-slate-900',
                                             'border-amber-300 ring-1 ring-amber-200 dark:border-amber-500/50 dark:ring-amber-500/20' => $stale,
                                             'border-slate-200 dark:border-slate-800' => ! $stale])>
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="font-semibold leading-snug text-slate-900 dark:text-white">{{ $e->destination }}</p>
                                        @if ($e->consultant)<x-avatar :user="$e->consultant" size="xs" />@endif
                                    </div>
                                    <p class="mt-0.5 truncate text-sm text-slate-600 dark:text-slate-400">{{ $e->customer->display_name }}</p>
                                    <div class="mt-3 flex items-center justify-between text-xs">
                                        <span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ money($e->expected_value) }}</span>
                                        <span class="text-slate-500">{{ $e->travellers() }} pax</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
                                        <span class="inline-flex items-center gap-1 text-slate-500"><x-hicon name="clock" class="size-3.5" />{{ $e->daysInStage() }}d in stage</span>
                                        @if ($stale)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-800 dark:bg-amber-400/15 dark:text-amber-300"><x-hicon name="bell-alert" class="size-3.5" />Quiet {{ (int) $e->last_activity_at?->diffInDays(now()) }}d — nudge</span>
                                        @endif
                                        @if ($e->status === \App\Enums\EnquiryStatus::Lost && $e->lost_reason)<span class="text-rose-700 dark:text-rose-400">{{ $e->lost_reason }}</span>@endif
                                    </div>
                                </article>
                            @endforeach
                            @if ($columns[$status->value]->isEmpty())
                                <p class="rounded-xl border-2 border-dashed border-slate-200 p-4 text-center text-xs text-slate-400 dark:border-slate-800">
                                    {{ $status === \App\Enums\EnquiryStatus::New ? 'No new enquiries — capture your first walk-in →' : 'Drop cards here' }}
                                </p>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @else
        <div class="card overflow-hidden">
            @if ($list->isEmpty())
                <x-empty-state icon="inbox" title="No enquiries yet" description="Capture your first walk-in or WhatsApp lead.">@can('pipeline.manage')<x-button icon="plus" wire:click="$set('panel', 'new-enquiry')">Capture your first walk-in →</x-button>@endcan</x-empty-state>
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Enquiry</th><th>Customer</th><th>Stage</th><th class="text-right">Value</th><th>Departure</th><th>Consultant</th><th>Last activity</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($list as $e)
                                <tr wire:key="row-{{ $e->id }}" wire:click="select({{ $e->id }})" class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                    <td><p class="font-semibold text-slate-900 dark:text-white">{{ $e->destination }}</p><p class="text-xs text-slate-500">{{ $e->reference }} · {{ $e->channel->label() }}</p></td>
                                    <td>{{ $e->customer->display_name }}</td>
                                    <td><x-badge :enum="$e->status" /></td>
                                    <td class="text-right tabular-nums">{{ money($e->expected_value) }}</td>
                                    <td class="text-xs">{{ fdate($e->departure_date) }}</td>
                                    <td>@if ($e->consultant)<x-avatar :user="$e->consultant" size="xs" />@endif</td>
                                    <td class="text-xs">@if ($e->isStale())<x-badge color="amber" icon="bell-alert" :label="'Quiet '.(int) $e->last_activity_at?->diffInDays(now()).'d'" size="xs" />@else<span class="text-slate-500">{{ $e->last_activity_at?->diffForHumans() }}</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Enquiry detail --}}
    <x-slide-over name="enquiry" :title="$selected ? $selected->destination.' · '.$selected->reference : 'Enquiry'" :description="$selected?->customer->display_name" width="max-w-xl">
        @if ($selected)
            <div class="space-y-6">
                <div class="flex flex-wrap items-center gap-2">
                    <x-badge :enum="$selected->status" />
                    <x-badge :enum="$selected->channel" />
                    @if ($selected->isStale())<x-badge color="amber" icon="bell-alert" label="Gone quiet" />@endif
                    <a href="{{ route('customers.show', $selected->customer) }}" wire:navigate class="link ml-auto text-sm">Customer 360 →</a>
                </div>

                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50"><dt class="text-xs text-slate-500">Dates</dt><dd class="font-medium">{{ fdate($selected->departure_date) }} – {{ fdate($selected->return_date) }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50"><dt class="text-xs text-slate-500">Travellers</dt><dd class="font-medium">{{ $selected->travellers_adults }} adults{{ $selected->travellers_children ? ', '.$selected->travellers_children.' children' : '' }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50"><dt class="text-xs text-slate-500">Budget</dt><dd class="font-medium">{{ $selected->budget ? money($selected->budget) : '—' }}</dd></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/50"><dt class="text-xs text-slate-500">Expected value · win chance</dt><dd class="font-medium">{{ money($selected->expected_value) }} · {{ $selected->probability }}%</dd></div>
                </dl>
                @if ($selected->notes)<p class="rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-800">{{ $selected->notes }}</p>@endif
                @if ($selected->lost_reason)<p class="rounded-xl bg-rose-50 p-3 text-sm text-rose-800 dark:bg-rose-400/10 dark:text-rose-200"><strong>Lost:</strong> {{ $selected->lost_reason }}</p>@endif

                @can('update', $selected)
                    <div>
                        <p class="form-label">Move to stage</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Enums\EnquiryStatus::cases() as $s)
                                @continue($s === $selected->status || $s === \App\Enums\EnquiryStatus::Won)
                                <x-button size="sm" variant="secondary" :icon="$s->icon()" wire:click="setStatus('{{ $s->value }}')">{{ $s->label() }}</x-button>
                            @endforeach
                        </div>
                    </div>
                @endcan

                @can('reassign', \App\Models\Enquiry::class)
                    <x-field label="Consultant" for="reassign">
                        <select id="reassign" class="form-input" x-on:change="$wire.reassign($event.target.value)">
                            @foreach ($consultants as $c)<option value="{{ $c->id }}" @selected($c->id === $selected->assigned_to)>{{ $c->name }}</option>@endforeach
                        </select>
                    </x-field>
                @endcan

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <p class="form-label !mb-0">Quotes</p>
                        @can('update', $selected)@if ($selected->isOpen())<x-button size="sm" icon="document-plus" wire:click="startQuote">Build quote</x-button>@endif @endcan
                    </div>
                    @forelse ($selected->quotes as $q)
                        <a href="{{ route('quotes.edit', $q) }}" wire:navigate class="mb-2 flex items-center justify-between rounded-xl border border-slate-200 p-3 hover:border-brand-400 dark:border-slate-800">
                            <div><p class="text-sm font-semibold">{{ $q->reference }} · {{ money($q->total_amount, $q->currency) }}</p><p class="text-xs text-slate-500">{{ $q->items_count }} items · valid until {{ fdate($q->valid_until) }}{{ $q->booking ? ' · booked as '.$q->booking->reference : '' }}</p></div>
                            <x-badge :enum="$q->status" />
                        </a>
                    @empty
                        <p class="rounded-xl border-2 border-dashed border-slate-200 p-4 text-center text-sm text-slate-500 dark:border-slate-800">No quote yet. Build one from supplier rates — markup is applied automatically.</p>
                    @endforelse
                </div>

                @if ($selected->interactions->isNotEmpty())
                    <div>
                        <p class="form-label">Recent activity</p>
                        <ul class="space-y-2">
                            @foreach ($selected->interactions as $i)
                                <li class="flex gap-2 text-sm"><x-hicon :name="$i->type->icon()" class="mt-0.5 size-4 text-slate-400" /><span><span class="font-medium">{{ $i->subject }}</span> <span class="text-xs text-slate-500">· {{ $i->occurred_at->diffForHumans() }}</span></span></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif
    </x-slide-over>

    {{-- Lost reason (required) --}}
    <x-slide-over name="lost" title="Why was this enquiry lost?" description="Required — it powers the lost-reason analysis in reports." width="max-w-md">
        <form wire:submit="confirmLost" id="lost-form" class="space-y-3">
            <div class="flex flex-wrap gap-2">
                @foreach (['Price too high', 'Booked with another agency', 'Trip postponed', 'Visa not approved', 'No response after quote', 'Changed destination'] as $reason)
                    <button type="button" wire:click="$set('lostReason', '{{ $reason }}')" @class(['rounded-full border px-3 py-1 text-xs font-medium', 'border-rose-500 bg-rose-50 text-rose-800 dark:bg-rose-400/10 dark:text-rose-200' => $lostReason === $reason, 'border-slate-200 dark:border-slate-700' => $lostReason !== $reason])>{{ $reason }}</button>
                @endforeach
            </div>
            <x-field label="Reason" for="lost-reason" error="lostReason" required><input id="lost-reason" wire:model="lostReason" class="form-input" placeholder="Or type your own"></x-field>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('panel', null)">Keep it open</x-button>
            <x-button variant="danger" type="submit" form="lost-form" icon="x-circle" loading="confirmLost">Mark as lost</x-button>
        </x-slot:footer>
    </x-slide-over>

    {{-- New enquiry --}}
    <x-slide-over name="new-enquiry" title="New enquiry" description="Capture it now, while the customer is on the line.">
        <form wire:submit="create" id="new-enq-form" class="space-y-4">
            @if (! $form['new_customer'])
                <x-field label="Customer" error="form.customer_id" required>
                    @if ($pickedCustomer)
                        <div class="flex items-center justify-between rounded-xl border border-brand-300 bg-brand-50 p-3 dark:border-brand-700 dark:bg-brand-400/10">
                            <span class="text-sm font-semibold">{{ $pickedCustomer->display_name }} <span class="font-normal text-slate-500">· {{ $pickedCustomer->phone }}</span></span>
                            <button type="button" wire:click="$set('form.customer_id', null)" class="link text-xs">Change</button>
                        </div>
                    @else
                        <input wire:model.live.debounce.250ms="customerSearch" type="search" class="form-input" placeholder="Search by name or phone…" aria-label="Search customers">
                        @if ($customerResults->isNotEmpty())
                            <ul class="mt-1 divide-y divide-slate-100 rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                                @foreach ($customerResults as $r)
                                    <li><button type="button" wire:click="pickCustomer({{ $r->id }})" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800">{{ $r->display_name }} <span class="text-slate-500">· {{ $r->phone }}</span></button></li>
                                @endforeach
                            </ul>
                        @elseif (strlen($customerSearch) >= 2)
                            <p class="mt-1 text-xs text-slate-500">No match.</p>
                        @endif
                        <button type="button" wire:click="$set('form.new_customer', true)" class="link mt-2 text-sm">+ New customer instead</button>
                    @endif
                </x-field>
            @else
                <div class="space-y-3 rounded-xl bg-slate-50 p-4 dark:bg-slate-800/40">
                    <div class="flex items-center justify-between"><p class="text-sm font-semibold">New customer</p><button type="button" wire:click="$set('form.new_customer', false)" class="link text-xs">Pick existing</button></div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-field label="First name" for="ne-fn" error="form.first_name" required><input id="ne-fn" wire:model="form.first_name" class="form-input"></x-field>
                        <x-field label="Last name" for="ne-ln" error="form.last_name" required><input id="ne-ln" wire:model="form.last_name" class="form-input"></x-field>
                    </div>
                    <x-field label="Phone / WhatsApp" for="ne-ph" error="form.phone" required><input id="ne-ph" type="tel" wire:model="form.phone" class="form-input" placeholder="+254 7XX XXX XXX"></x-field>
                </div>
            @endif
            <x-field label="Destination" for="ne-dest" error="form.destination" required>
                <input id="ne-dest" wire:model.blur="form.destination" list="ne-destinations" class="form-input" placeholder="e.g. Zanzibar">
                <datalist id="ne-destinations">@foreach (['Maasai Mara', 'Diani Beach', 'Zanzibar', 'Dubai', 'Cape Town', 'Mauritius', 'Seychelles', 'Amboseli', 'Lamu', 'London'] as $d)<option value="{{ $d }}">@endforeach</datalist>
            </x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Departure" for="ne-dep" error="form.departure_date"><input id="ne-dep" type="date" wire:model="form.departure_date" class="form-input"></x-field>
                <x-field label="Return" for="ne-ret" error="form.return_date"><input id="ne-ret" type="date" wire:model="form.return_date" class="form-input"></x-field>
                <x-field label="Adults" for="ne-ad"><input id="ne-ad" type="number" min="1" wire:model="form.travellers_adults" class="form-input"></x-field>
                <x-field label="Children" for="ne-ch"><input id="ne-ch" type="number" min="0" wire:model="form.travellers_children" class="form-input"></x-field>
                <x-field label="Budget (KES)" for="ne-bud" error="form.budget"><input id="ne-bud" type="number" min="0" step="1000" wire:model="form.budget" class="form-input"></x-field>
                <x-field label="Channel" for="ne-chan"><select id="ne-chan" wire:model="form.channel" class="form-input">@foreach (CustomerSource::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
            </div>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="new-enq-form" loading="create">Capture enquiry</x-button></x-slot:footer>
    </x-slide-over>
</div>
