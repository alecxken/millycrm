<?php

use App\Enums\BookingStatus;
use App\Enums\ContactChannel;
use App\Enums\CustomerSource;
use App\Enums\CustomerType;
use App\Enums\LifecycleStage;
use App\Enums\Role;
use App\Enums\TravelStyle;
use App\Models\Customer;
use App\Models\SavedView;
use App\Models\Tag;
use App\Models\User;
use App\Services\CustomerService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Customers')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public array $stage = [];

    #[Url]
    public string $source = '';

    #[Url]
    public string $tag = '';

    #[Url]
    public string $consultant = '';

    #[Url]
    public string $country = '';

    #[Url]
    public string $sort = 'recent';

    public ?string $panel = null;

    public string $viewName = '';

    public bool $moreDetails = false;

    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();

        if (request()->boolean('new')) {
            $parts = explode(' ', trim((string) request('name')), 2);
            $this->form['first_name'] = $parts[0] ?? '';
            $this->form['last_name'] = $parts[1] ?? '';
            $this->panel = 'new-customer';
        }
    }

    public function resetForm(): void
    {
        $this->form = [
            'type' => 'individual', 'first_name' => '', 'last_name' => '', 'company_name' => '',
            'phone' => '', 'email' => '', 'source' => 'walk_in', 'preferred_contact_channel' => 'whatsapp',
            'marketing_consent' => false, 'city' => 'Nairobi', 'country' => 'Kenya', 'nationality' => 'Kenyan',
            'whatsapp' => '', 'date_of_birth' => null, 'travel_style' => '', 'budget_band' => '', 'notes' => '',
            'assigned_to' => auth()->id(),
        ];
        $this->moreDetails = false;
        $this->resetValidation();
    }

    public function updated($property): void
    {
        if (in_array($property, ['search', 'stage', 'source', 'tag', 'consultant', 'country', 'sort'], true) || str_starts_with($property, 'stage')) {
            $this->resetPage();
        }
    }

    public function toggleStage(string $value): void
    {
        $this->stage = in_array($value, $this->stage, true)
            ? array_values(array_diff($this->stage, [$value]))
            : [...$this->stage, $value];
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'stage', 'source', 'tag', 'consultant', 'country');
        $this->resetPage();
    }

    public function applyView(int $id): void
    {
        $view = SavedView::where('user_id', auth()->id())->findOrFail($id);
        $this->clearFilters();
        foreach (['stage', 'source', 'tag', 'consultant', 'country'] as $key) {
            if (isset($view->filters[$key])) {
                $value = $view->filters[$key];
                $this->{$key} = $key === 'stage' ? (array) $value : (string) (is_array($value) ? ($value[0] ?? '') : $value);
            }
        }
        if (isset($view->filters['country']) && is_array($view->filters['country']) && count($view->filters['country']) > 1) {
            $this->country = implode(',', $view->filters['country']);
        }
    }

    public function saveView(): void
    {
        $this->validate(['viewName' => 'required|string|max:60']);

        SavedView::create([
            'user_id' => auth()->id(),
            'name' => $this->viewName,
            'context' => 'customers',
            'filters' => array_filter(['stage' => $this->stage, 'source' => $this->source, 'tag' => $this->tag, 'consultant' => $this->consultant, 'country' => $this->country, 'search' => $this->search]),
        ]);

        $this->viewName = '';
        $this->panel = null;
        $this->dispatch('toast', message: 'View saved. Find it under "Saved views".');
    }

    public function deleteView(int $id): void
    {
        SavedView::where('user_id', auth()->id())->whereKey($id)->delete();
        $this->dispatch('toast', message: 'Saved view removed.');
    }

    public function create(CustomerService $customers): void
    {
        $this->authorize('create', Customer::class);

        $data = $this->validate([
            'form.type' => ['required', Rule::enum(CustomerType::class)],
            'form.first_name' => ['required', 'string', 'max:80'],
            'form.last_name' => ['required', 'string', 'max:80'],
            'form.company_name' => ['nullable', 'required_unless:form.type,individual', 'string', 'max:120'],
            'form.phone' => ['required_without:form.email', 'nullable', 'string', 'max:40'],
            'form.email' => ['required_without:form.phone', 'nullable', 'email', 'max:120', Rule::unique('customers', 'email')->whereNull('deleted_at')],
            'form.source' => ['required', Rule::enum(CustomerSource::class)],
            'form.preferred_contact_channel' => ['required', Rule::enum(ContactChannel::class)],
            'form.marketing_consent' => ['boolean'],
            'form.whatsapp' => ['nullable', 'string', 'max:40'],
            'form.city' => ['nullable', 'string', 'max:80'],
            'form.country' => ['nullable', 'string', 'max:80'],
            'form.nationality' => ['nullable', 'string', 'max:80'],
            'form.date_of_birth' => ['nullable', 'date', 'before:today'],
            'form.travel_style' => ['nullable', Rule::enum(TravelStyle::class)],
            'form.budget_band' => ['nullable', 'in:economy,mid,premium,luxury'],
            'form.notes' => ['nullable', 'string', 'max:2000'],
            'form.assigned_to' => ['nullable', 'exists:users,id'],
        ], attributes: ['form.first_name' => 'first name', 'form.last_name' => 'last name', 'form.company_name' => 'company / group name', 'form.phone' => 'phone', 'form.email' => 'email']);

        $data = collect($data['form'])->map(fn ($v) => $v === '' ? null : $v)->all();
        if (! auth()->user()->isManagerOrAbove()) {
            $data['assigned_to'] = auth()->id();
        }

        $customer = $customers->create($data);

        $this->panel = null;
        $this->resetForm();
        session()->flash('toast', ['message' => "{$customer->display_name} added. Capture their first enquiry next.", 'type' => 'success']);
        $this->redirectRoute('customers.show', $customer, navigate: true);
    }

    public function with(): array
    {
        $user = auth()->user();

        $query = Customer::query()
            ->visibleTo($user)
            ->with(['consultant:id,name,avatar_color', 'tags:id,name,color'])
            ->withCount(['bookings' => fn ($q) => $q->where('status', '!=', BookingStatus::Cancelled)])
            ->withSum(['bookings as lifetime_value' => fn ($q) => $q->where('status', '!=', BookingStatus::Cancelled)], 'total_amount')
            ->search($this->search)
            ->when($this->stage, fn ($q) => $q->whereIn('lifecycle_stage', $this->stage))
            ->when($this->source, fn ($q) => $q->where('source', $this->source))
            ->when($this->tag, fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($this->tag)))
            ->when($this->consultant, fn ($q) => $q->where('assigned_to', $this->consultant))
            ->when($this->country, fn ($q) => $q->whereIn('country', explode(',', $this->country)));

        match ($this->sort) {
            'name' => $query->orderBy('first_name')->orderBy('last_name'),
            'value' => $query->orderByDesc('lifetime_value'),
            'contact' => $query->orderByRaw('last_contacted_at is null')->orderBy('last_contacted_at'),
            default => $query->orderByDesc('last_contacted_at'),
        };

        $stageCounts = Customer::visibleTo($user)->selectRaw('lifecycle_stage, count(*) as c')->groupBy('lifecycle_stage')->pluck('c', 'lifecycle_stage');

        return [
            'customers' => $query->paginate(15),
            'stageCounts' => $stageCounts,
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'consultants' => User::role(Role::Consultant->value)->orderBy('name')->get(['id', 'name']),
            'countries' => Customer::visibleTo($user)->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
            'views' => SavedView::where('user_id', $user->id)->where('context', 'customers')->orderBy('name')->get(),
            'filtered' => $this->search || $this->stage || $this->source || $this->tag || $this->consultant || $this->country,
        ];
    }
}; ?>

<div>
    <x-page-header title="Customers" subtitle="Everyone who has ever asked us about a trip, all in one place.">
        <x-slot:actions>
            <x-dropdown align="right" width="64">
                <x-slot name="trigger">
                    <x-button variant="secondary" icon="bookmark">Saved views</x-button>
                </x-slot>
                <x-slot name="content">
                    <div class="py-1">
                        @forelse ($views as $view)
                            <div class="group flex items-center justify-between px-3 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800">
                                <button type="button" wire:click="applyView({{ $view->id }})" class="flex-1 truncate text-left text-sm">{{ $view->name }}</button>
                                <button type="button" wire:click="deleteView({{ $view->id }})" wire:confirm="Delete the saved view “{{ $view->name }}”?" class="rounded p-1 text-slate-400 opacity-0 hover:text-rose-600 group-hover:opacity-100 focus:opacity-100"><span class="sr-only">Delete</span><x-hicon name="trash" class="size-4" /></button>
                            </div>
                        @empty
                            <p class="px-3 py-2 text-sm text-slate-500">No saved views yet. Filter the list, then save it.</p>
                        @endforelse
                        <div class="border-t border-slate-100 px-3 pt-2 pb-1 dark:border-slate-700">
                            <button type="button" wire:click="$set('panel', 'save-view')" class="link text-sm" @disabled(! $filtered)>+ Save current filters</button>
                        </div>
                    </div>
                </x-slot>
            </x-dropdown>
            @can('customers.manage')
                <x-button icon="user-plus" wire:click="$set('panel', 'new-customer')">New customer</x-button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Lifecycle stage chips double as a funnel summary --}}
    <div class="mb-4 flex gap-2 overflow-x-auto pb-1">
        @foreach (LifecycleStage::cases() as $s)
            @php($active = in_array($s->value, $stage, true))
            <button type="button" wire:click="toggleStage('{{ $s->value }}')" aria-pressed="{{ $active ? 'true' : 'false' }}"
                    @class(['inline-flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                        'border-brand-600 bg-brand-50 text-brand-800 dark:border-brand-500 dark:bg-brand-400/10 dark:text-brand-200' => $active,
                        'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300' => ! $active])>
                <x-hicon :name="$s->icon()" class="size-4" />
                {{ $s->label() }}
                <span class="rounded-full bg-slate-100 px-1.5 text-xs tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-400">{{ $stageCounts[$s->value] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="card mb-4 grid grid-cols-2 gap-3 p-3 md:grid-cols-3 xl:grid-cols-6">
        <div class="relative col-span-2 md:col-span-3 xl:col-span-2">
            <x-hicon name="magnifying-glass" class="pointer-events-none absolute top-2.5 left-3 size-5 text-slate-400" />
            <label for="customer-search" class="sr-only">Search customers</label>
            <input id="customer-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Name, company, phone or email" class="form-input pl-10">
        </div>
        <label class="sr-only" for="f-source">Source</label>
        <select id="f-source" wire:model.live="source" class="form-input">
            <option value="">All sources</option>
            @foreach (CustomerSource::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
        </select>
        <label class="sr-only" for="f-tag">Tag</label>
        <select id="f-tag" wire:model.live="tag" class="form-input">
            <option value="">All tags</option>
            @foreach ($tags as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
        </select>
        @if (auth()->user()->isManagerOrAbove() || ! auth()->user()->hasRole('consultant'))
            <label class="sr-only" for="f-consultant">Consultant</label>
            <select id="f-consultant" wire:model.live="consultant" class="form-input">
                <option value="">All consultants</option>
                @foreach ($consultants as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        @endif
        <label class="sr-only" for="f-country">Country</label>
        <select id="f-country" wire:model.live="country" class="form-input">
            <option value="">All countries</option>
            @if ($country && str_contains($country, ','))<option value="{{ $country }}">Several countries</option>@endif
            @foreach ($countries as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach
        </select>
    </div>

    <div class="mb-3 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400">
        <p>
            <span class="font-semibold text-slate-900 dark:text-white">{{ number_format($customers->total()) }}</span> {{ Str::plural('customer', $customers->total()) }}
            @if ($filtered) · <button type="button" wire:click="clearFilters" class="link">Clear filters</button>@endif
        </p>
        <label class="flex items-center gap-2">
            <span class="hidden sm:inline">Sort</span>
            <select wire:model.live="sort" class="form-input w-auto py-1.5">
                <option value="recent">Recently contacted</option>
                <option value="contact">Longest without contact</option>
                <option value="value">Lifetime value</option>
                <option value="name">Name A–Z</option>
            </select>
        </label>
    </div>

    <div class="card overflow-hidden" wire:loading.class="opacity-60" wire:target="search,stage,source,tag,consultant,country,sort,toggleStage,applyView,clearFilters,gotoPage,nextPage,previousPage">
        @if ($customers->isEmpty())
            @if ($filtered)
                <x-empty-state icon="magnifying-glass" title="No customers match these filters" description="Try removing a filter or searching by phone number instead.">
                    <x-button variant="secondary" wire:click="clearFilters">Clear filters</x-button>
                </x-empty-state>
            @else
                <x-empty-state icon="users" title="No customers yet" description="Every walk-in, WhatsApp message and referral starts here.">
                    @can('customers.manage')<x-button icon="user-plus" wire:click="$set('panel', 'new-customer')">Capture your first customer →</x-button>@endcan
                </x-empty-state>
            @endif
        @else
            {{-- Desktop table --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="table-base">
                    <thead class="bg-slate-50 dark:bg-slate-900/60">
                        <tr><th>Customer</th><th>Stage</th><th>Consultant</th><th class="text-right">Trips</th><th class="text-right">Lifetime value</th><th>Last contact</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($customers as $customer)
                            <tr wire:key="c-{{ $customer->id }}" class="group relative hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <x-avatar :initials="$customer->initials" :color="['individual' => 'teal', 'corporate' => 'violet', 'group' => 'amber'][$customer->type->value]" size="sm" :title="false" />
                                        <div class="min-w-0">
                                            <a href="{{ route('customers.show', $customer) }}" wire:navigate class="font-semibold text-slate-900 after:absolute after:inset-0 hover:text-brand-700 dark:text-white dark:hover:text-brand-400">{{ $customer->display_name }}</a>
                                            <div class="flex items-center gap-1.5 text-xs text-slate-500">
                                                @if ($customer->type !== CustomerType::Individual)<span>{{ $customer->full_name }} ·</span>@endif
                                                <span>{{ $customer->city ? $customer->city.', ' : '' }}{{ $customer->country }}</span>
                                                @foreach ($customer->tags->take(2) as $t)<x-badge :color="$t->color" :label="$t->name" size="xs" />@endforeach
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><x-badge :enum="$customer->lifecycle_stage" /></td>
                                <td>@if ($customer->consultant)<div class="flex items-center gap-2"><x-avatar :user="$customer->consultant" size="xs" /><span class="text-xs">{{ $customer->consultant->firstName() }}</span></div>@else<span class="text-xs text-slate-400">Unassigned</span>@endif</td>
                                <td class="text-right tabular-nums">{{ $customer->bookings_count }}</td>
                                <td class="text-right font-medium tabular-nums">{{ $customer->lifetime_value ? money($customer->lifetime_value) : '—' }}</td>
                                <td class="text-xs {{ $customer->last_contacted_at?->lt(now()->subMonths(6)) ? 'text-amber-700 dark:text-amber-400' : 'text-slate-500' }}">{{ $customer->last_contacted_at?->diffForHumans() ?? 'Never' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{-- Mobile cards --}}
            <ul class="divide-y divide-slate-100 md:hidden dark:divide-slate-800">
                @foreach ($customers as $customer)
                    <li wire:key="cm-{{ $customer->id }}">
                        <a href="{{ route('customers.show', $customer) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 active:bg-slate-50 dark:active:bg-slate-800">
                            <x-avatar :initials="$customer->initials" :color="['individual' => 'teal', 'corporate' => 'violet', 'group' => 'amber'][$customer->type->value]" size="md" :title="false" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold">{{ $customer->display_name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $customer->phone }} · {{ $customer->bookings_count }} trips</p>
                            </div>
                            <x-badge :enum="$customer->lifecycle_stage" size="xs" />
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $customers->links() }}</div>
        @endif
    </div>

    {{-- New customer: short form first, details on demand (progressive disclosure) --}}
    <x-slide-over name="new-customer" title="New customer" description="Just the essentials — you can add the rest later.">
        <form wire:submit="create" id="new-customer-form" class="space-y-4">
            <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Customer type">
                @foreach (CustomerType::cases() as $t)
                    <label @class(['flex cursor-pointer flex-col items-center gap-1 rounded-xl border p-3 text-sm font-medium transition', 'border-brand-600 bg-brand-50 text-brand-800 dark:bg-brand-400/10 dark:text-brand-200' => $form['type'] === $t->value, 'border-slate-200 dark:border-slate-700' => $form['type'] !== $t->value])>
                        <input type="radio" wire:model.live="form.type" value="{{ $t->value }}" class="sr-only">
                        <x-hicon :name="$t->icon()" />{{ $t->label() }}
                    </label>
                @endforeach
            </div>
            @if ($form['type'] !== 'individual')
                <x-field :label="$form['type'] === 'corporate' ? 'Company name' : 'Group name'" for="nc-company" error="form.company_name" required>
                    <input id="nc-company" wire:model.blur="form.company_name" class="form-input" placeholder="{{ $form['type'] === 'corporate' ? 'e.g. Savannah Tech Ltd' : 'e.g. St. Andrew\'s Church Youth' }}">
                </x-field>
            @endif
            <div class="grid grid-cols-2 gap-3">
                <x-field :label="$form['type'] === 'individual' ? 'First name' : 'Contact first name'" for="nc-first" error="form.first_name" required>
                    <input id="nc-first" wire:model.blur="form.first_name" class="form-input" autocomplete="off">
                </x-field>
                <x-field label="Last name" for="nc-last" error="form.last_name" required>
                    <input id="nc-last" wire:model.blur="form.last_name" class="form-input" autocomplete="off">
                </x-field>
            </div>
            <x-field label="Phone / WhatsApp" for="nc-phone" error="form.phone" hint="Phone or email is required.">
                <input id="nc-phone" wire:model.blur="form.phone" type="tel" class="form-input" placeholder="+254 7XX XXX XXX">
            </x-field>
            <x-field label="Email" for="nc-email" error="form.email">
                <input id="nc-email" wire:model.blur="form.email" type="email" class="form-input">
            </x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="How did they find us?" for="nc-source" error="form.source">
                    <select id="nc-source" wire:model="form.source" class="form-input">
                        @foreach (CustomerSource::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </x-field>
                <x-field label="Preferred channel" for="nc-channel">
                    <select id="nc-channel" wire:model="form.preferred_contact_channel" class="form-input">
                        @foreach (ContactChannel::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </x-field>
            </div>
            <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                <input type="checkbox" wire:model="form.marketing_consent" class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                <span class="text-sm"><span class="font-medium">Customer agrees to receive offers</span><br><span class="text-slate-500">Recorded with today's date. Without consent they're excluded from every campaign.</span></span>
            </label>

            <button type="button" wire:click="$toggle('moreDetails')" class="flex items-center gap-1 text-sm font-semibold text-brand-700 dark:text-brand-400" aria-expanded="{{ $moreDetails ? 'true' : 'false' }}">
                <x-hicon :name="$moreDetails ? 'chevron-up' : 'chevron-down'" class="size-4" /> {{ $moreDetails ? 'Fewer details' : 'More details' }}
            </button>
            @if ($moreDetails)
                <div class="space-y-4 rounded-xl bg-slate-50 p-4 dark:bg-slate-800/40">
                    <div class="grid grid-cols-2 gap-3">
                        <x-field label="City" for="nc-city"><input id="nc-city" wire:model="form.city" class="form-input"></x-field>
                        <x-field label="Country" for="nc-country"><input id="nc-country" wire:model="form.country" class="form-input"></x-field>
                        <x-field label="Nationality" for="nc-nat"><input id="nc-nat" wire:model="form.nationality" class="form-input"></x-field>
                        <x-field label="Date of birth" for="nc-dob" error="form.date_of_birth"><input id="nc-dob" type="date" wire:model="form.date_of_birth" class="form-input"></x-field>
                        <x-field label="Travel style" for="nc-style">
                            <select id="nc-style" wire:model="form.travel_style" class="form-input"><option value="">—</option>@foreach (TravelStyle::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                        </x-field>
                        <x-field label="Budget" for="nc-budget">
                            <select id="nc-budget" wire:model="form.budget_band" class="form-input"><option value="">—</option>@foreach (\App\Enums\BudgetBand::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                        </x-field>
                    </div>
                    @if (auth()->user()->isManagerOrAbove())
                        <x-field label="Assign to" for="nc-assign">
                            <select id="nc-assign" wire:model="form.assigned_to" class="form-input">@foreach ($consultants as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
                        </x-field>
                    @endif
                    <x-field label="Notes" for="nc-notes"><textarea id="nc-notes" wire:model="form.notes" rows="3" class="form-input"></textarea></x-field>
                </div>
            @endif
        </form>
        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button>
            <x-button type="submit" form="new-customer-form" icon="check" loading="create">Save customer</x-button>
        </x-slot:footer>
    </x-slide-over>

    <x-slide-over name="save-view" title="Save this view" description="Keep these filters one click away." width="max-w-md">
        <form wire:submit="saveView" id="save-view-form">
            <x-field label="View name" for="view-name" error="viewName" required>
                <input id="view-name" wire:model="viewName" class="form-input" placeholder="e.g. My VIPs in Mombasa">
            </x-field>
        </form>
        <x-slot:footer>
            <x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button>
            <x-button type="submit" form="save-view-form" loading="saveView">Save view</x-button>
        </x-slot:footer>
    </x-slide-over>
</div>
