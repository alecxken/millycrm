<?php

use App\Enums\BookingStatus;
use App\Enums\BudgetBand;
use App\Enums\ContactChannel;
use App\Enums\CustomerSource;
use App\Enums\Direction;
use App\Enums\InteractionType;
use App\Enums\Priority;
use App\Enums\Role;
use App\Enums\TaskType;
use App\Enums\TicketCategory;
use App\Enums\TravelStyle;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\LifecycleService;
use App\Services\PrivacyService;
use App\Services\TicketService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component
{
    public Customer $customer;

    #[Url]
    public string $tab = 'timeline';

    public ?string $panel = null;

    public array $log = [];

    public array $email = [];

    public array $task = [];

    public array $enquiry = [];

    public array $ticket = [];

    public array $details = [];

    public array $prefs = [];

    public array $contact = [];

    public array $tagIds = [];

    public bool $passportRevealed = false;

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->customer = $customer;
        $this->resetForms();
    }

    public function rendering($view): void
    {
        $view->title($this->customer->display_name);
    }

    public function resetForms(): void
    {
        $c = $this->customer->loadMissing(['preference', 'tags:id']);
        $this->log = ['type' => 'call', 'direction' => 'outbound', 'subject' => '', 'body' => '', 'follow_up' => false, 'follow_up_at' => now()->addDays(2)->format('Y-m-d')];
        $this->email = ['subject' => 'Hello from WanderLink Travel', 'body' => "Hi {$c->first_name},\n\n"];
        $this->task = ['title' => '', 'due_at' => now()->addDay()->format('Y-m-d\T10:00'), 'priority' => 'normal'];
        $this->enquiry = ['destination' => $c->preference?->preferred_destinations[0] ?? '', 'departure_date' => now()->addMonth()->format('Y-m-d'), 'return_date' => now()->addMonth()->addDays(5)->format('Y-m-d'), 'travellers_adults' => 2, 'travellers_children' => 0, 'budget' => '', 'trip_type' => $c->preference?->travel_style?->value ?? '', 'channel' => $c->source->value];
        $this->ticket = ['subject' => '', 'category' => 'general', 'priority' => 'normal', 'booking_id' => '', 'description' => ''];
        $this->details = $c->only(['first_name', 'last_name', 'company_name', 'email', 'phone', 'whatsapp', 'city', 'country', 'nationality', 'source', 'preferred_contact_channel', 'marketing_consent', 'assigned_to', 'notes', 'passport_number'])
            + ['date_of_birth' => $c->date_of_birth?->format('Y-m-d'), 'passport_expiry' => $c->passport_expiry?->format('Y-m-d')];
        $this->details['source'] = $c->source->value;
        $this->details['preferred_contact_channel'] = $c->preferred_contact_channel->value;
        $p = $c->preference;
        $this->prefs = [
            'seat_preference' => $p?->seat_preference ?? '', 'meal_preference' => $p?->meal_preference ?? '',
            'budget_band' => $p?->budget_band?->value ?? '', 'travel_style' => $p?->travel_style?->value ?? '',
            'preferred_airlines' => implode(', ', $p?->preferred_airlines ?? []), 'preferred_destinations' => implode(', ', $p?->preferred_destinations ?? []),
            'special_needs' => $p?->special_needs ?? '',
        ];
        $this->contact = ['name' => '', 'role' => '', 'email' => '', 'phone' => ''];
        $this->tagIds = $c->tags->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->resetValidation();
    }

    public function open(string $panel): void
    {
        $this->resetForms();
        $this->panel = $panel;
    }

    private function close(string $message): void
    {
        $this->panel = null;
        $this->customer->refresh();
        $this->resetForms();
        $this->dispatch('toast', message: $message);
    }

    public function logInteraction(): void
    {
        $this->authorize('update', $this->customer);
        $data = $this->validate([
            'log.type' => ['required', Rule::enum(InteractionType::class)],
            'log.direction' => ['required', Rule::enum(Direction::class)],
            'log.subject' => ['required', 'string', 'max:150'],
            'log.body' => ['nullable', 'string', 'max:5000'],
            'log.follow_up' => ['boolean'],
            'log.follow_up_at' => ['required_if:log.follow_up,true', 'nullable', 'date', 'after_or_equal:today'],
        ], attributes: ['log.subject' => 'summary', 'log.follow_up_at' => 'follow-up date'])['log'];

        $this->customer->interactions()->create([
            'user_id' => auth()->id(),
            'type' => $data['type'], 'direction' => $data['direction'],
            'subject' => $data['subject'], 'body' => $data['body'] ?: null,
            'occurred_at' => now(),
        ]);

        if ($data['follow_up']) {
            Task::create([
                'customer_id' => $this->customer->id, 'assigned_to' => auth()->id(), 'created_by' => auth()->id(),
                'type' => TaskType::FollowUp, 'title' => 'Follow up: '.$data['subject'],
                'due_at' => \Illuminate\Support\Carbon::parse($data['follow_up_at'])->setTime(10, 0), 'priority' => Priority::Normal,
            ]);
        }

        $this->close(InteractionType::from($data['type'])->label().' logged'.($data['follow_up'] ? ' and follow-up scheduled.' : '.'));
    }

    public function sendEmail(): void
    {
        $this->authorize('update', $this->customer);
        $data = $this->validate(['email.subject' => 'required|string|max:150', 'email.body' => 'required|string|max:5000'])['email'];
        abort_if(blank($this->customer->email), 422, 'This customer has no email address.');

        // Mail uses the "log" driver in this prototype: the message is written to storage/logs.
        Mail::raw($data['body'], fn ($m) => $m->to($this->customer->email)->subject($data['subject']));

        $this->customer->interactions()->create([
            'user_id' => auth()->id(), 'type' => InteractionType::Email, 'direction' => Direction::Outbound,
            'subject' => $data['subject'], 'body' => $data['body'], 'occurred_at' => now(),
        ]);

        $this->close('Email sent (logged) and added to the timeline.');
    }

    public function addTask(): void
    {
        $this->authorize('update', $this->customer);
        $data = $this->validate([
            'task.title' => 'required|string|max:150',
            'task.due_at' => 'required|date',
            'task.priority' => ['required', Rule::enum(Priority::class)],
        ], attributes: ['task.title' => 'task'])['task'];

        Task::create([...$data, 'customer_id' => $this->customer->id, 'assigned_to' => $this->customer->assigned_to ?? auth()->id(), 'created_by' => auth()->id(), 'type' => TaskType::General]);
        $this->close('Task added to My Day.');
    }

    public function createEnquiry(): void
    {
        $this->authorize('create', Enquiry::class);
        $data = $this->validate([
            'enquiry.destination' => 'required|string|max:120',
            'enquiry.departure_date' => 'nullable|date|after_or_equal:today',
            'enquiry.return_date' => 'nullable|date|after_or_equal:enquiry.departure_date',
            'enquiry.travellers_adults' => 'required|integer|min:1|max:200',
            'enquiry.travellers_children' => 'required|integer|min:0|max:200',
            'enquiry.budget' => 'nullable|numeric|min:0',
            'enquiry.trip_type' => ['nullable', Rule::enum(TravelStyle::class)],
            'enquiry.channel' => ['required', Rule::enum(CustomerSource::class)],
        ], attributes: ['enquiry.destination' => 'destination', 'enquiry.return_date' => 'return date'])['enquiry'];

        $enquiry = Enquiry::create([
            ...collect($data)->map(fn ($v) => $v === '' ? null : $v)->all(),
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->customer->assigned_to ?? auth()->id(),
            'expected_value' => $data['budget'] ?: 0,
            'probability' => 10,
            'stage_changed_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->customer->interactions()->create([
            'user_id' => auth()->id(), 'type' => InteractionType::Note, 'direction' => Direction::Inbound,
            'subject' => "New enquiry {$enquiry->reference}: {$enquiry->destination}", 'occurred_at' => now(),
            'related_type' => Enquiry::class, 'related_id' => $enquiry->id,
        ]);

        $this->close("Enquiry {$enquiry->reference} added to the pipeline.");
    }

    public function openTicket(TicketService $tickets): void
    {
        $this->authorize('create', \App\Models\ServiceTicket::class);
        $data = $this->validate([
            'ticket.subject' => 'required|string|max:150',
            'ticket.category' => ['required', Rule::enum(TicketCategory::class)],
            'ticket.priority' => ['required', Rule::enum(Priority::class)],
            'ticket.booking_id' => ['nullable', Rule::exists('bookings', 'id')->where('customer_id', $this->customer->id)],
            'ticket.description' => 'nullable|string|max:5000',
        ], attributes: ['ticket.subject' => 'subject'])['ticket'];

        $support = User::role(Role::Support->value)->first();
        $ticket = $tickets->open([...$data, 'booking_id' => $data['booking_id'] ?: null, 'customer_id' => $this->customer->id, 'assigned_to' => $support?->id]);
        $this->close("Ticket {$ticket->reference} opened. SLA: ".fdate($ticket->sla_due_at, true).'.');
    }

    public function saveDetails(CustomerService $customers): void
    {
        $this->authorize('update', $this->customer);
        $data = $this->validate([
            'details.first_name' => 'required|string|max:80',
            'details.last_name' => 'required|string|max:80',
            'details.company_name' => 'nullable|string|max:120',
            'details.email' => ['nullable', 'email', 'max:120', Rule::unique('customers', 'email')->ignore($this->customer->id)->whereNull('deleted_at')],
            'details.phone' => 'nullable|string|max:40',
            'details.whatsapp' => 'nullable|string|max:40',
            'details.city' => 'nullable|string|max:80',
            'details.country' => 'nullable|string|max:80',
            'details.nationality' => 'nullable|string|max:80',
            'details.date_of_birth' => 'nullable|date|before:today',
            'details.source' => ['required', Rule::enum(CustomerSource::class)],
            'details.preferred_contact_channel' => ['required', Rule::enum(ContactChannel::class)],
            'details.marketing_consent' => 'boolean',
            'details.assigned_to' => 'nullable|exists:users,id',
            'details.notes' => 'nullable|string|max:5000',
            'details.passport_number' => 'nullable|string|max:20',
            'details.passport_expiry' => 'nullable|date',
        ])['details'];

        $data = collect($data)->map(fn ($v) => $v === '' ? null : $v)->all();
        if (! auth()->user()->isManagerOrAbove()) {
            unset($data['assigned_to']);
        }

        $customers->update($this->customer, $data);
        $this->customer->tags()->sync($this->tagIds);
        $this->close('Customer details saved.');
    }

    public function savePreferences(): void
    {
        $this->authorize('update', $this->customer);
        $data = $this->validate([
            'prefs.seat_preference' => 'nullable|string|max:20',
            'prefs.meal_preference' => 'nullable|string|max:40',
            'prefs.budget_band' => ['nullable', Rule::enum(BudgetBand::class)],
            'prefs.travel_style' => ['nullable', Rule::enum(TravelStyle::class)],
            'prefs.preferred_airlines' => 'nullable|string|max:255',
            'prefs.preferred_destinations' => 'nullable|string|max:255',
            'prefs.special_needs' => 'nullable|string|max:2000',
        ])['prefs'];

        $split = fn ($v) => collect(explode(',', (string) $v))->map(fn ($s) => trim($s))->filter()->values()->all();
        $this->customer->preference()->updateOrCreate([], [
            ...collect($data)->map(fn ($v) => $v === '' ? null : $v)->all(),
            'preferred_airlines' => $split($data['preferred_airlines']),
            'preferred_destinations' => $split($data['preferred_destinations']),
        ]);
        $this->customer->unsetRelation('preference');
        $this->close('Preferences saved.');
    }

    public function addContact(): void
    {
        $this->authorize('update', $this->customer);
        $data = $this->validate(['contact.name' => 'required|string|max:120', 'contact.role' => 'nullable|string|max:80', 'contact.email' => 'nullable|email', 'contact.phone' => 'nullable|string|max:40'])['contact'];
        $this->customer->contacts()->create($data);
        $this->close('Contact added.');
    }

    public function removeContact(int $id): void
    {
        $this->authorize('update', $this->customer);
        $this->customer->contacts()->whereKey($id)->delete();
        $this->dispatch('toast', message: 'Contact removed.');
    }

    public function completeTask(int $id): void
    {
        $task = $this->customer->tasks()->findOrFail($id);
        $this->authorize('update', $task);
        $task->update(['completed_at' => now()]);
        $this->dispatch('toast', message: 'Task completed.', undo: ['id' => $this->getId(), 'method' => 'reopenTask', 'args' => [$id]]);
    }

    public function reopenTask(int $id): void
    {
        $task = $this->customer->tasks()->findOrFail($id);
        $this->authorize('update', $task);
        $task->update(['completed_at' => null]);
    }

    public function revealPassport(): void
    {
        $this->authorize('update', $this->customer);
        activity()->performedOn($this->customer)->causedBy(auth()->user())->log('viewed passport number');
        $this->passportRevealed = true;
    }

    public function recheckLifecycle(LifecycleService $lifecycle): void
    {
        $changed = $lifecycle->evaluate($this->customer);
        $this->customer->refresh();
        $this->dispatch('toast', message: $changed ? "Stage updated to {$this->customer->lifecycle_stage->label()}." : 'Stage is already up to date.');
    }

    public function anonymise(PrivacyService $privacy): void
    {
        $this->authorize('delete', $this->customer);
        $privacy->anonymise($this->customer);
        session()->flash('toast', ['message' => 'Customer anonymised and archived. Financial records were kept for audit.', 'type' => 'success']);
        $this->redirectRoute('customers.index', navigate: true);
    }

    public function with(LifecycleService $lifecycle): array
    {
        $c = $this->customer;
        $c->load([
            'consultant:id,name,avatar_color,email',
            'preference', 'contacts', 'tags:id,name,color',
            'bookings' => fn ($q) => $q->latest('start_date')->with('feedback'),
            'enquiries' => fn ($q) => $q->latest()->with(['latestQuote', 'consultant:id,name,avatar_color']),
            'interactions' => fn ($q) => $q->latest('occurred_at')->with('user:id,name,avatar_color'),
            'tickets' => fn ($q) => $q->latest(),
            'feedback' => fn ($q) => $q->latest('submitted_at'),
            'tasks' => fn ($q) => $q->whereNull('completed_at')->orderBy('due_at'),
        ]);

        $timeline = collect()
            ->merge($c->interactions->map(fn ($i) => [
                'at' => $i->occurred_at, 'icon' => $i->type->icon(), 'color' => $i->type->color(),
                'title' => $i->subject ?? $i->type->label(), 'body' => $i->body,
                'meta' => $i->type->label().' · '.$i->direction->label().($i->user ? ' · '.$i->user->name : ''),
                'kind' => 'interaction',
            ]))
            ->merge($c->bookings->map(fn ($b) => [
                'at' => $b->start_date->copy()->setTime(8, 0), 'icon' => 'paper-airplane', 'color' => 'sky',
                'title' => ($b->start_date->isFuture() ? 'Departs for ' : 'Travelled to ').$b->destination,
                'body' => fdate($b->start_date).' – '.fdate($b->end_date).' · '.money($b->total_amount, $b->currency),
                'meta' => 'Trip · '.$b->reference.' · '.$b->status->label(), 'kind' => 'trip', 'url' => route('bookings.show', $b),
            ]))
            ->merge($c->tickets->map(fn ($t) => [
                'at' => $t->created_at, 'icon' => 'lifebuoy', 'color' => $t->isOpen() ? 'rose' : 'slate',
                'title' => 'Ticket: '.$t->subject, 'body' => $t->resolution,
                'meta' => 'Service · '.$t->reference.' · '.$t->status->label(), 'kind' => 'ticket',
                'url' => auth()->user()->can('tickets.view') ? route('tickets.show', $t) : null,
            ]))
            ->merge($c->feedback->map(fn ($f) => [
                'at' => $f->submitted_at, 'icon' => 'heart', 'color' => ['promoter' => 'emerald', 'passive' => 'slate', 'detractor' => 'rose'][$f->npsGroup()],
                'title' => "Feedback: NPS {$f->nps_score} · ".str_repeat('★', $f->rating), 'body' => $f->comment ? '“'.$f->comment.'”' : null,
                'meta' => 'Feedback · '.ucfirst($f->npsGroup()), 'kind' => 'feedback',
            ]))
            ->sortByDesc('at')->values();

        $trips = $c->bookings->where('status', '!=', BookingStatus::Cancelled);
        $ltv = $trips->sum(fn ($b) => (float) $b->total_amount * $b->currency->toKes());
        $latestNps = $c->feedback->first()?->nps_score;

        return [
            'timeline' => $timeline,
            'ltv' => $ltv,
            'tripCount' => $trips->count(),
            'nps' => $latestNps,
            'nextStage' => $this->nextStageHint($trips->count(), $ltv),
            'consultants' => User::role(Role::Consultant->value)->orderBy('name')->get(['id', 'name']),
            'allTags' => Tag::orderBy('name')->get(['id', 'name', 'color']),
        ];
    }

    private function nextStageHint(int $trips, float $ltv): ?string
    {
        return match (true) {
            $this->customer->lifecycle_stage === \App\Enums\LifecycleStage::Vip => null,
            $trips === 0 => 'Becomes a Customer on their first booking',
            $trips === 1 => 'Becomes Repeat on their second booking',
            default => 'VIP at '.LifecycleService::VIP_BOOKINGS.' trips or '.money(LifecycleService::VIP_LIFETIME_VALUE, compact: true).' lifetime value ('.max(0, LifecycleService::VIP_BOOKINGS - $trips).' trips or '.money(max(0, LifecycleService::VIP_LIFETIME_VALUE - $ltv), compact: true).' to go)',
        };
    }
}; ?>

@php($c = $customer)
<div>
    <nav class="mb-4 text-sm" aria-label="Breadcrumb">
        <a href="{{ route('customers.index') }}" wire:navigate class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"><x-hicon name="arrow-left" class="size-4" /> Customers</a>
    </nav>

    {{-- Header card --}}
    <section class="card overflow-hidden">
        <div class="h-2 bg-gradient-to-r from-brand-700 via-brand-500 to-sand-500"></div>
        <div class="p-5 sm:p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <x-avatar :initials="$c->initials" :color="['individual' => 'teal', 'corporate' => 'violet', 'group' => 'amber'][$c->type->value]" size="xl" :title="false" />
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-bold tracking-tight">{{ $c->display_name }}</h1>
                            <x-badge :enum="$c->lifecycle_stage" />
                            @if ($c->type->value !== 'individual')<x-badge :enum="$c->type" />@endif
                        </div>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            @if ($c->type->value !== 'individual'){{ $c->full_name }} · @endif
                            {{ collect([$c->city, $c->country])->filter()->implode(', ') }} · via {{ $c->source->label() }} · customer since {{ $c->created_at->format('M Y') }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($c->tags as $t)<x-badge :color="$t->color" icon="tag" :label="$t->name" size="xs" />@endforeach
                            @if ($c->marketing_consent)
                                <x-badge color="emerald" icon="check-circle" label="Marketing consent" size="xs" title="Given {{ fdate($c->consent_at) }}" />
                            @else
                                <x-badge color="slate" icon="no-symbol" label="No marketing consent" size="xs" />
                            @endif
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2 text-sm">
                            @if ($c->phone)<a href="tel:{{ $c->phone }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 font-medium hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700"><x-hicon name="phone" class="size-4" />{{ $c->phone }}</a>@endif
                            @if ($c->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $c->whatsapp) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 font-medium text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-400/10 dark:text-emerald-300"><x-hicon name="chat-bubble-left-right" class="size-4" />WhatsApp</a>@endif
                            @if ($c->email)<a href="mailto:{{ $c->email }}" class="inline-flex items-center gap-1.5 rounded-lg bg-slate-100 px-2.5 py-1 font-medium hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700"><x-hicon name="envelope" class="size-4" /><span class="max-w-48 truncate">{{ $c->email }}</span></a>@endif
                        </div>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-3 rounded-xl bg-slate-50 px-3 py-2 dark:bg-slate-800/50">
                    @if ($c->consultant)
                        <x-avatar :user="$c->consultant" size="sm" />
                        <div class="text-sm"><p class="text-xs text-slate-500">Consultant</p><p class="font-semibold">{{ $c->consultant->name }}</p></div>
                    @else
                        <p class="text-sm text-slate-500">No consultant assigned</p>
                    @endif
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"><dt class="text-xs font-medium text-slate-500">Lifetime value</dt><dd class="mt-1 text-lg font-bold tabular-nums">{{ money($ltv) }}</dd></div>
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"><dt class="text-xs font-medium text-slate-500">Trips</dt><dd class="mt-1 text-lg font-bold tabular-nums">{{ $tripCount }}</dd></div>
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"><dt class="text-xs font-medium text-slate-500">Last contact</dt><dd class="mt-1 text-lg font-bold">{{ $c->last_contacted_at?->diffForHumans(short: true) ?? 'Never' }}</dd></div>
                <div class="rounded-xl border border-slate-200 p-3 dark:border-slate-800"><dt class="text-xs font-medium text-slate-500">Latest NPS</dt><dd class="mt-1 flex items-center gap-2 text-lg font-bold">{{ $nps ?? '—' }} @if ($nps !== null)<x-badge :color="$nps >= 9 ? 'emerald' : ($nps >= 7 ? 'slate' : 'rose')" :icon="$nps >= 9 ? 'face-smile' : ($nps >= 7 ? 'minus-circle' : 'face-frown')" :label="$nps >= 9 ? 'Promoter' : ($nps >= 7 ? 'Passive' : 'Detractor')" size="xs" />@endif</dd></div>
            </dl>
            @if ($nextStage)
                <p class="mt-3 flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400"><x-hicon name="arrow-trending-up" class="size-4 text-brand-600" /> Lifecycle: {{ $nextStage }}.</p>
            @endif

            {{-- Quick actions: every one opens a slide-over, never a new page --}}
            @can('update', $c)
                <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-5 dark:border-slate-800">
                    <x-button icon="phone" wire:click="open('log-call')" loading="open">Log call</x-button>
                    <x-button variant="secondary" icon="envelope" wire:click="open('email')" :disabled="! $c->email">Send email</x-button>
                    <x-button variant="secondary" icon="clipboard-document-check" wire:click="open('task')">Add task</x-button>
                    @can('pipeline.manage')<x-button variant="accent" icon="plus" wire:click="open('enquiry')">New enquiry</x-button>@endcan
                    <x-button variant="secondary" icon="lifebuoy" wire:click="open('ticket')">Open ticket</x-button>
                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger"><x-button variant="ghost" icon="ellipsis-horizontal">More</x-button></x-slot>
                        <x-slot name="content">
                            <button type="button" wire:click="open('details')" class="block w-full px-4 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Edit details, tags & consent</button>
                            <button type="button" wire:click="recheckLifecycle" class="block w-full px-4 py-2 text-left text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Re-check lifecycle stage</button>
                            @can('export', $c)
                                <a href="{{ route('customers.export', $c) }}" class="block px-4 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Export personal data (JSON)</a>
                                <button type="button" wire:click="anonymise" wire:confirm="Anonymise {{ $c->display_name }}? Their personal details will be permanently removed. Bookings and payments stay for audit. This cannot be undone."
                                        class="block w-full px-4 py-2 text-left text-sm text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-400/10">Anonymise (right to erasure)…</button>
                            @endcan
                        </x-slot>
                    </x-dropdown>
                </div>
            @endcan
        </div>
    </section>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 lg:col-span-2">
            {{-- Tabs --}}
            <div class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200 dark:border-slate-800" role="tablist">
                @foreach (['timeline' => ['Timeline', 'clock', $timeline->count()], 'trips' => ['Trips', 'paper-airplane', $c->bookings->count() + $c->enquiries->count()], 'preferences' => ['Preferences', 'adjustments-horizontal', null], 'documents' => ['Documents', 'identification', $c->passportExpiresSoon() ? '!' : null], 'contacts' => ['Contacts', 'user-group', $c->contacts->count()]] as $key => [$label, $icon, $count])
                    <button type="button" role="tab" wire:click="$set('tab', '{{ $key }}')" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"
                            @class(['-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-medium transition', 'border-brand-600 text-brand-700 dark:border-brand-400 dark:text-brand-300' => $tab === $key, 'border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white' => $tab !== $key])>
                        <x-hicon :name="$icon" class="size-4" />{{ $label }}
                        @if ($count)<span @class(['rounded-full px-1.5 text-xs', 'bg-rose-100 text-rose-700 dark:bg-rose-400/20 dark:text-rose-300' => $count === '!', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400' => $count !== '!'])>{{ $count }}</span>@endif
                    </button>
                @endforeach
            </div>

            <div wire:loading.class="opacity-50" wire:target="tab">
            @if ($tab === 'timeline')
                @if ($timeline->isEmpty())
                    <div class="card"><x-empty-state icon="chat-bubble-left-right" title="No history yet" description="Log the first call or WhatsApp chat so the whole team knows the story.">@can('update', $c)<x-button icon="phone" wire:click="open('log-call')">Log the first interaction</x-button>@endcan</x-empty-state></div>
                @else
                    <ol class="relative space-y-4 border-l-2 border-slate-200 pl-6 dark:border-slate-800">
                        @foreach ($timeline->take(60) as $item)
                            <li class="relative">
                                <span class="absolute top-3 -left-[35px] flex size-7 items-center justify-center rounded-full bg-white ring-2 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                                    <x-badge :color="$item['color']" :icon="$item['icon']" label="" class="!p-1 !ring-0" />
                                </span>
                                <div class="card p-4">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <p class="font-medium text-slate-900 dark:text-white">
                                            @if (! empty($item['url']))<a href="{{ $item['url'] }}" wire:navigate class="hover:text-brand-700 dark:hover:text-brand-400">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif
                                        </p>
                                        <time class="text-xs text-slate-500" datetime="{{ $item['at']->toIso8601String() }}" title="{{ fdate($item['at'], true) }}">{{ fdate($item['at']) }}</time>
                                    </div>
                                    @if ($item['body'])<p class="mt-1 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ Str::limit($item['body'], 400) }}</p>@endif
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $item['meta'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

            @elseif ($tab === 'trips')
                <div class="space-y-6">
                    <x-card title="Enquiries" :padding="false">
                        @forelse ($c->enquiries as $e)
                            <a href="{{ route('pipeline', ['enquiry' => $e->id]) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ $e->destination }} <span class="text-xs font-normal text-slate-500">· {{ $e->reference }}</span></p>
                                    <p class="text-xs text-slate-500">{{ fdate($e->departure_date) }} · {{ $e->travellers() }} travellers · {{ money($e->expected_value) }}{{ $e->lost_reason ? ' · Lost: '.$e->lost_reason : '' }}</p>
                                </div>
                                <x-badge :enum="$e->status" />
                            </a>
                        @empty
                            <x-empty-state icon="inbox" title="No enquiries yet">@can('pipeline.manage')<x-button size="sm" icon="plus" wire:click="open('enquiry')">Capture an enquiry</x-button>@endcan</x-empty-state>
                        @endforelse
                    </x-card>
                    <x-card title="Bookings" :padding="false">
                        @forelse ($c->bookings as $b)
                            <a href="{{ route('bookings.show', $b) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                                <x-hicon name="paper-airplane" class="size-5 text-sky-600" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate font-medium">{{ $b->destination }} <span class="text-xs font-normal text-slate-500">· {{ $b->reference }}</span></p>
                                    <p class="text-xs text-slate-500">{{ fdate($b->start_date) }} – {{ fdate($b->end_date) }} · {{ money($b->total_amount, $b->currency) }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-1"><x-badge :enum="$b->status" size="xs" /><x-badge :enum="$b->payment_status" size="xs" /></div>
                            </a>
                        @empty
                            <x-empty-state icon="paper-airplane" title="No trips booked yet" description="Convert an accepted quote into a booking from the pipeline." />
                        @endforelse
                    </x-card>
                </div>

            @elseif ($tab === 'preferences')
                @php($p = $c->preference)
                <x-card title="Travel preferences" subtitle="Use these to personalise every quote">
                    <x-slot:actions>@can('update', $c)<x-button size="sm" variant="secondary" icon="pencil" wire:click="open('prefs')">Edit</x-button>@endcan</x-slot:actions>
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        @foreach ([
                            ['Travel style', e($p?->travel_style?->label())],
                            ['Budget band', e($p?->budget_band?->label())],
                            ['Seat', e($p?->seat_preference ? ucfirst($p->seat_preference) : '')],
                            ['Meals', e($p?->meal_preference)],
                            ['Preferred airlines', e(implode(', ', $p?->preferred_airlines ?? []))],
                            ['Dream destinations', e(implode(', ', $p?->preferred_destinations ?? []))],
                        ] as [$label, $value])
                            <div><dt class="text-xs font-medium text-slate-500">{{ $label }}</dt><dd class="mt-1 text-sm font-medium">{!! $value ?: '<span class="text-slate-400">Not recorded</span>' !!}</dd></div>
                        @endforeach
                        <div class="sm:col-span-2"><dt class="text-xs font-medium text-slate-500">Special needs</dt><dd class="mt-1 text-sm">{{ $p?->special_needs ?: 'None recorded' }}</dd></div>
                    </dl>
                </x-card>

            @elseif ($tab === 'documents')
                <x-card title="Travel documents" subtitle="Passport numbers are encrypted at rest and every view is audited">
                    @if ($c->passportExpiresSoon())
                        <div class="mb-4 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200" role="alert">
                            <x-hicon name="exclamation-triangle" class="size-5" />
                            <div><p class="font-semibold">Passport {{ $c->passport_expiry->isPast() ? 'has expired' : 'expires '.$c->passport_expiry->diffForHumans() }} ({{ fdate($c->passport_expiry) }})</p><p>Most destinations require 6 months' validity. Remind the customer to renew before booking.</p></div>
                        </div>
                    @endif
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium text-slate-500">Passport number</dt>
                            <dd class="mt-1 flex items-center gap-2 font-mono text-sm">
                                @if (! $c->passport_number)<span class="font-sans text-slate-400">Not recorded</span>
                                @elseif ($passportRevealed){{ $c->passport_number }}
                                @else •••••{{ substr($c->passport_number, -3) }}
                                    @can('update', $c)<button type="button" wire:click="revealPassport" class="link font-sans text-xs">Reveal</button>@endcan
                                @endif
                            </dd>
                        </div>
                        <div><dt class="text-xs font-medium text-slate-500">Expiry</dt><dd class="mt-1 text-sm font-medium">{{ fdate($c->passport_expiry) }}</dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Nationality</dt><dd class="mt-1 text-sm">{{ $c->nationality ?? '—' }}</dd></div>
                        <div><dt class="text-xs font-medium text-slate-500">Date of birth</dt><dd class="mt-1 text-sm">{{ fdate($c->date_of_birth) }}</dd></div>
                    </dl>
                </x-card>

            @else
                <x-card title="Contacts" subtitle="People we deal with for this account" :padding="false">
                    <x-slot:actions>@can('update', $c)<x-button size="sm" variant="secondary" icon="plus" wire:click="open('contact')">Add contact</x-button>@endcan</x-slot:actions>
                    @forelse ($c->contacts as $ct)
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
                            <x-avatar :initials="collect(explode(' ', $ct->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('')" color="slate" size="sm" :title="false" />
                            <div class="min-w-0 flex-1"><p class="font-medium">{{ $ct->name }}</p><p class="text-xs text-slate-500">{{ collect([$ct->role, $ct->phone, $ct->email])->filter()->implode(' · ') }}</p></div>
                            @can('update', $c)<button type="button" wire:click="removeContact({{ $ct->id }})" wire:confirm="Remove {{ $ct->name }}?" class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><span class="sr-only">Remove</span><x-hicon name="trash" class="size-4" /></button>@endcan
                        </div>
                    @empty
                        <x-empty-state icon="user-group" title="No contacts yet" description="Add the travel coordinator, group leader or assistant who books on their behalf." />
                    @endforelse
                </x-card>
            @endif
            </div>
        </div>

        {{-- Side column: what to do next --}}
        <aside class="space-y-6">
            <x-card title="Open tasks" :padding="false">
                @forelse ($c->tasks as $t)
                    <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800" wire:key="task-{{ $t->id }}">
                        <input type="checkbox" wire:click="completeTask({{ $t->id }})" class="mt-0.5 size-5 rounded-md border-slate-300 text-brand-700 focus:ring-brand-600" aria-label="Complete {{ $t->title }}">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ $t->title }}</p>
                            <p @class(['text-xs', 'font-semibold text-rose-700 dark:text-rose-400' => $t->isOverdue(), 'text-slate-500' => ! $t->isOverdue()])>{{ $t->isOverdue() ? 'Overdue · ' : 'Due ' }}{{ fdate($t->due_at, true) }}</p>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="check-badge" title="Nothing pending" class="!py-8" />
                @endforelse
            </x-card>
            @if ($c->notes)
                <x-card title="Notes"><p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $c->notes }}</p></x-card>
            @endif
            <x-card title="At a glance">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Prefers</dt><dd class="font-medium"><x-badge :enum="$c->preferred_contact_channel" size="xs" /></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Travel style</dt><dd class="font-medium">{{ $c->preference?->travel_style?->label() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Budget</dt><dd class="font-medium">{{ $c->preference?->budget_band?->label() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Open tickets</dt><dd class="font-medium">{{ $c->tickets->filter->isOpen()->count() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">Birthday</dt><dd class="font-medium">{{ $c->date_of_birth?->format('j M') ?? '—' }} @if ($c->date_of_birth?->isBirthday())🎂@endif</dd></div>
                </dl>
            </x-card>
        </aside>
    </div>

    {{-- ---------------- Slide-overs ---------------- --}}
    <x-slide-over name="log-call" title="Log an interaction" description="Calls, WhatsApp chats, meetings — keep the story in one place.">
        <form wire:submit="logInteraction" id="log-form" class="space-y-4">
            <div class="grid grid-cols-3 gap-2">
                @foreach (InteractionType::cases() as $type)
                    <label @class(['flex cursor-pointer flex-col items-center gap-1 rounded-xl border p-2 text-xs font-medium', 'border-brand-600 bg-brand-50 text-brand-800 dark:bg-brand-400/10 dark:text-brand-200' => $log['type'] === $type->value, 'border-slate-200 dark:border-slate-700' => $log['type'] !== $type->value])>
                        <input type="radio" wire:model.live="log.type" value="{{ $type->value }}" class="sr-only"><x-hicon :name="$type->icon()" />{{ $type->label() }}
                    </label>
                @endforeach
            </div>
            <x-field label="Direction">
                <div class="flex gap-4 text-sm">
                    @foreach (Direction::cases() as $d)<label class="flex items-center gap-2"><input type="radio" wire:model="log.direction" value="{{ $d->value }}" class="text-brand-700 focus:ring-brand-600">{{ $d->label() }}</label>@endforeach
                </div>
            </x-field>
            <x-field label="Summary" for="call-subject" error="log.subject" required><input id="call-subject" wire:model.blur="log.subject" class="form-input" placeholder="e.g. Discussed Zanzibar dates"></x-field>
            <x-field label="Notes" for="call-body"><textarea id="call-body" wire:model="log.body" rows="5" class="form-input" placeholder="What was agreed? What does the customer need next?"></textarea></x-field>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="log.follow_up" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600"> Schedule a follow-up</label>
            @if ($log['follow_up'])
                <x-field label="Follow up on" for="call-fu" error="log.follow_up_at"><input id="call-fu" type="date" wire:model="log.follow_up_at" class="form-input"></x-field>
            @endif
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="log-form" loading="logInteraction">Save to timeline</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="email" title="Send email" description="To {{ $c->email }}" width="max-w-2xl">
        <form wire:submit="sendEmail" id="email-form" class="space-y-4">
            <x-field label="Subject" for="em-subject" error="email.subject" required><input id="em-subject" wire:model.live.debounce.300ms="email.subject" class="form-input"></x-field>
            <x-field label="Message" for="em-body" error="email.body" required><textarea id="em-body" wire:model.live.debounce.300ms="email.body" rows="8" class="form-input"></textarea></x-field>
            <div>
                <p class="form-label">Preview</p>
                <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                    <div class="bg-brand-700 px-5 py-3 text-sm font-semibold text-white">WanderLink Travel</div>
                    <div class="bg-white p-5 text-sm text-slate-800 dark:bg-slate-950 dark:text-slate-200">
                        <p class="mb-3 font-semibold">{{ $email['subject'] }}</p>
                        <p class="whitespace-pre-line">{{ $email['body'] }}</p>
                        <p class="mt-4 text-slate-500">— {{ auth()->user()->name }}, {{ auth()->user()->job_title }}</p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Prototype: messages use the log mail driver and are written to <code>storage/logs</code>.</p>
            </div>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="email-form" icon="paper-airplane" loading="sendEmail">Send</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="task" title="Add a task" width="max-w-md">
        <form wire:submit="addTask" id="task-form" class="space-y-4">
            <x-field label="What needs doing?" for="t-title" error="task.title" required><input id="t-title" wire:model.blur="task.title" class="form-input" placeholder="e.g. Send visa checklist"></x-field>
            <x-field label="Due" for="t-due" error="task.due_at"><input id="t-due" type="datetime-local" wire:model="task.due_at" class="form-input"></x-field>
            <x-field label="Priority" for="t-prio"><select id="t-prio" wire:model="task.priority" class="form-input">@foreach (Priority::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="task-form" loading="addTask">Add task</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="enquiry" title="New enquiry" description="For {{ $c->display_name }}">
        <form wire:submit="createEnquiry" id="enq-form" class="space-y-4">
            <x-field label="Destination" for="e-dest" error="enquiry.destination" required>
                <input id="e-dest" wire:model.blur="enquiry.destination" list="destinations" class="form-input" placeholder="e.g. Maasai Mara">
                <datalist id="destinations">@foreach (['Maasai Mara', 'Diani Beach', 'Zanzibar', 'Dubai', 'Cape Town', 'Mauritius', 'Seychelles', 'Amboseli', 'Lamu', 'London', 'Rwanda Gorilla Trek'] as $d)<option value="{{ $d }}">@endforeach</datalist>
            </x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Departure" for="e-dep" error="enquiry.departure_date"><input id="e-dep" type="date" wire:model="enquiry.departure_date" class="form-input"></x-field>
                <x-field label="Return" for="e-ret" error="enquiry.return_date"><input id="e-ret" type="date" wire:model="enquiry.return_date" class="form-input"></x-field>
                <x-field label="Adults" for="e-ad" error="enquiry.travellers_adults"><input id="e-ad" type="number" min="1" wire:model="enquiry.travellers_adults" class="form-input"></x-field>
                <x-field label="Children" for="e-ch" error="enquiry.travellers_children"><input id="e-ch" type="number" min="0" wire:model="enquiry.travellers_children" class="form-input"></x-field>
                <x-field label="Budget (KES)" for="e-bud" error="enquiry.budget"><input id="e-bud" type="number" min="0" step="1000" wire:model="enquiry.budget" class="form-input"></x-field>
                <x-field label="Trip type" for="e-type"><select id="e-type" wire:model="enquiry.trip_type" class="form-input"><option value="">—</option>@foreach (TravelStyle::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
            </div>
            <x-field label="Channel" for="e-ch2"><select id="e-ch2" wire:model="enquiry.channel" class="form-input">@foreach (CustomerSource::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="enq-form" loading="createEnquiry">Add to pipeline</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="ticket" title="Open a service ticket" description="Support is notified and the SLA clock starts.">
        <form wire:submit="openTicket" id="ticket-form" class="space-y-4">
            <x-field label="Subject" for="tk-sub" error="ticket.subject" required><input id="tk-sub" wire:model.blur="ticket.subject" class="form-input" placeholder="e.g. Lost passport in Dubai"></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Category" for="tk-cat"><select id="tk-cat" wire:model="ticket.category" class="form-input">@foreach (TicketCategory::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
                <x-field label="Priority" for="tk-pri" hint="Urgent = 4h SLA, High = 24h"><select id="tk-pri" wire:model="ticket.priority" class="form-input">@foreach (Priority::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
            </div>
            <x-field label="Related booking" for="tk-bk" error="ticket.booking_id"><select id="tk-bk" wire:model="ticket.booking_id" class="form-input"><option value="">None</option>@foreach ($c->bookings as $b)<option value="{{ $b->id }}">{{ $b->reference }} · {{ $b->destination }} ({{ fdate($b->start_date) }})</option>@endforeach</select></x-field>
            <x-field label="What happened?" for="tk-desc"><textarea id="tk-desc" wire:model="ticket.description" rows="5" class="form-input"></textarea></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="ticket-form" loading="openTicket">Open ticket</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="details" title="Edit customer" width="max-w-2xl">
        <form wire:submit="saveDetails" id="details-form" class="space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <x-field label="First name" for="d-fn" error="details.first_name" required><input id="d-fn" wire:model="details.first_name" class="form-input"></x-field>
                <x-field label="Last name" for="d-ln" error="details.last_name" required><input id="d-ln" wire:model="details.last_name" class="form-input"></x-field>
                @if ($c->type->value !== 'individual')<x-field label="Company / group" for="d-co" class="sm:col-span-2"><input id="d-co" wire:model="details.company_name" class="form-input"></x-field>@endif
                <x-field label="Email" for="d-em" error="details.email"><input id="d-em" type="email" wire:model="details.email" class="form-input"></x-field>
                <x-field label="Phone" for="d-ph"><input id="d-ph" wire:model="details.phone" class="form-input"></x-field>
                <x-field label="WhatsApp" for="d-wa"><input id="d-wa" wire:model="details.whatsapp" class="form-input"></x-field>
                <x-field label="Date of birth" for="d-dob" error="details.date_of_birth"><input id="d-dob" type="date" wire:model="details.date_of_birth" class="form-input"></x-field>
                <x-field label="City" for="d-city"><input id="d-city" wire:model="details.city" class="form-input"></x-field>
                <x-field label="Country" for="d-country"><input id="d-country" wire:model="details.country" class="form-input"></x-field>
                <x-field label="Passport number" for="d-pp" hint="Encrypted at rest"><input id="d-pp" wire:model="details.passport_number" class="form-input font-mono" autocomplete="off"></x-field>
                <x-field label="Passport expiry" for="d-ppx"><input id="d-ppx" type="date" wire:model="details.passport_expiry" class="form-input"></x-field>
                <x-field label="Source" for="d-src"><select id="d-src" wire:model="details.source" class="form-input">@foreach (CustomerSource::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
                <x-field label="Preferred channel" for="d-pc"><select id="d-pc" wire:model="details.preferred_contact_channel" class="form-input">@foreach (ContactChannel::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
                @if (auth()->user()->isManagerOrAbove())
                    <x-field label="Consultant" for="d-as" class="sm:col-span-2"><select id="d-as" wire:model="details.assigned_to" class="form-input"><option value="">Unassigned</option>@foreach ($consultants as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></x-field>
                @endif
            </div>
            <fieldset>
                <legend class="form-label">Tags</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($allTags as $t)
                        <label @class(['cursor-pointer rounded-full border px-3 py-1 text-xs font-medium', 'border-brand-600 bg-brand-50 text-brand-800 dark:bg-brand-400/10 dark:text-brand-200' => in_array((string) $t->id, $tagIds, true), 'border-slate-200 dark:border-slate-700' => ! in_array((string) $t->id, $tagIds, true)])>
                            <input type="checkbox" wire:model.live="tagIds" value="{{ $t->id }}" class="sr-only">{{ $t->name }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input type="checkbox" wire:model="details.marketing_consent" class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                <span class="text-sm"><span class="font-medium">Marketing consent</span><br><span class="text-slate-500">{{ $c->consent_at ? 'Currently given on '.fdate($c->consent_at).'.' : 'Not given.' }} Changes are timestamped and audited.</span></span>
            </label>
            <x-field label="Notes" for="d-notes"><textarea id="d-notes" wire:model="details.notes" rows="3" class="form-input"></textarea></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="details-form" loading="saveDetails">Save changes</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="prefs" title="Travel preferences">
        <form wire:submit="savePreferences" id="prefs-form" class="space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Travel style" for="p-style"><select id="p-style" wire:model="prefs.travel_style" class="form-input"><option value="">—</option>@foreach (TravelStyle::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
                <x-field label="Budget" for="p-bud"><select id="p-bud" wire:model="prefs.budget_band" class="form-input"><option value="">—</option>@foreach (BudgetBand::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
                <x-field label="Seat" for="p-seat"><select id="p-seat" wire:model="prefs.seat_preference" class="form-input"><option value="">—</option><option value="window">Window</option><option value="aisle">Aisle</option><option value="no preference">No preference</option></select></x-field>
                <x-field label="Meals" for="p-meal"><input id="p-meal" wire:model="prefs.meal_preference" class="form-input" placeholder="e.g. Halal"></x-field>
            </div>
            <x-field label="Preferred airlines" for="p-air" hint="Comma separated"><input id="p-air" wire:model="prefs.preferred_airlines" class="form-input"></x-field>
            <x-field label="Dream destinations" for="p-dest" hint="Comma separated"><input id="p-dest" wire:model="prefs.preferred_destinations" class="form-input"></x-field>
            <x-field label="Special needs" for="p-sn"><textarea id="p-sn" wire:model="prefs.special_needs" rows="3" class="form-input"></textarea></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="prefs-form" loading="savePreferences">Save preferences</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="contact" title="Add contact" width="max-w-md">
        <form wire:submit="addContact" id="contact-form" class="space-y-4">
            <x-field label="Name" for="ct-n" error="contact.name" required><input id="ct-n" wire:model="contact.name" class="form-input"></x-field>
            <x-field label="Role" for="ct-r"><input id="ct-r" wire:model="contact.role" class="form-input" placeholder="e.g. Travel coordinator"></x-field>
            <x-field label="Email" for="ct-e" error="contact.email"><input id="ct-e" type="email" wire:model="contact.email" class="form-input"></x-field>
            <x-field label="Phone" for="ct-p"><input id="ct-p" wire:model="contact.phone" class="form-input"></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="contact-form" loading="addContact">Add contact</x-button></x-slot:footer>
    </x-slide-over>
</div>
