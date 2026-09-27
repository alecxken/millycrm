<?php

use App\Enums\BookingStatus;
use App\Enums\BudgetBand;
use App\Enums\CustomerSource;
use App\Enums\CustomerType;
use App\Enums\LifecycleStage;
use App\Enums\TravelStyle;
use App\Models\Customer;
use App\Models\Segment;
use App\Models\Tag;
use App\Services\SegmentService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Segments')] class extends Component
{
    #[Url(as: 'segment')]
    public ?int $segmentId = null;

    public string $name = '';

    public string $description = '';

    public array $rules = [];

    public function mount(): void
    {
        $this->segmentId ? $this->load($this->segmentId) : $this->blank();
    }

    public function blank(): void
    {
        $this->segmentId = null;
        $this->name = '';
        $this->description = '';
        $this->rules = [
            'lifecycle_stage' => [], 'type' => [], 'source' => [], 'travel_style' => [], 'budget_band' => [], 'tags' => [], 'country' => [],
            'marketing_consent' => '', 'min_bookings' => '', 'min_lifetime_value' => '', 'travelled_within_months' => '', 'not_travelled_within_months' => '', 'birthday_month' => '',
        ];
        $this->resetValidation();
    }

    public function load(int $id): void
    {
        $segment = Segment::findOrFail($id);
        $this->blank();
        $this->segmentId = $segment->id;
        $this->name = $segment->name;
        $this->description = (string) $segment->description;
        foreach ($segment->rules as $key => $value) {
            if (array_key_exists($key, $this->rules)) {
                $this->rules[$key] = is_array($this->rules[$key]) ? array_map('strval', (array) $value) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
            }
        }
    }

    public function toggle(string $rule, string $value): void
    {
        $current = $this->rules[$rule] ?? [];
        $this->rules[$rule] = in_array($value, $current, true) ? array_values(array_diff($current, [$value])) : [...$current, $value];
    }

    public function save(SegmentService $segments): void
    {
        $this->authorize($this->segmentId ? 'update' : 'create', $this->segmentId ? Segment::find($this->segmentId) : Segment::class);
        $this->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
            'rules.min_bookings' => 'nullable|integer|min:0',
            'rules.min_lifetime_value' => 'nullable|numeric|min:0',
            'rules.travelled_within_months' => 'nullable|integer|min:1|max:60',
            'rules.not_travelled_within_months' => 'nullable|integer|min:1|max:60',
            'rules.birthday_month' => 'nullable|integer|between:1,12',
        ]);

        $rules = $segments->normalise($this->castRules());
        $segment = Segment::updateOrCreate(['id' => $this->segmentId], ['name' => $this->name, 'description' => $this->description ?: null, 'rules' => $rules, 'created_by' => auth()->id()]);
        $this->segmentId = $segment->id;
        $this->dispatch('toast', message: "Segment “{$segment->name}” saved.");
    }

    public function delete(): void
    {
        $segment = Segment::findOrFail($this->segmentId);
        $this->authorize('delete', $segment);
        if ($segment->campaigns()->exists()) {
            $this->dispatch('toast', message: 'This segment is used by a campaign and can’t be deleted.', type: 'error');

            return;
        }
        $segment->delete();
        $this->blank();
        $this->dispatch('toast', message: 'Segment deleted.');
    }

    private function castRules(): array
    {
        $r = $this->rules;
        foreach (['min_bookings', 'travelled_within_months', 'not_travelled_within_months', 'birthday_month'] as $k) {
            $r[$k] = $r[$k] === '' ? null : (int) $r[$k];
        }
        $r['min_lifetime_value'] = $r['min_lifetime_value'] === '' ? null : (float) $r['min_lifetime_value'];
        $r['marketing_consent'] = $r['marketing_consent'] === '' ? null : $r['marketing_consent'] === '1';
        $r['tags'] = array_map('intval', $r['tags']);

        return $r;
    }

    public function with(SegmentService $segments): array
    {
        $rules = $segments->normalise($this->castRules());
        $query = $segments->query($rules);
        $count = (clone $query)->count();
        $consented = (clone $query)->where('marketing_consent', true)->count();

        return [
            'segments' => Segment::withCount('campaigns')->orderBy('name')->get()->map(fn ($s) => ['model' => $s, 'count' => $segments->count($s->rules)]),
            'count' => $count,
            'consented' => $consented,
            'total' => Customer::count(),
            'sample' => (clone $query)->with('preference:id,customer_id,travel_style')->withSum(['bookings as ltv' => fn ($q) => $q->where('status', '!=', BookingStatus::Cancelled)], 'total_amount')->orderByDesc('ltv')->limit(8)->get(),
            'summary' => $segments->describe($rules),
            'json' => json_encode($rules ?: new stdClass, JSON_PRETTY_PRINT),
            'tags' => Tag::orderBy('name')->get(['id', 'name']),
            'countries' => Customer::whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
        ];
    }
}; ?>

<div>
    <x-page-header title="Segment builder" subtitle="Combine rules to find the right customers. The count updates as you build.">
        <x-slot:actions>
            <x-button variant="secondary" icon="plus" wire:click="blank">New segment</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-4">
        <aside class="xl:col-span-1">
            <x-card title="Saved segments" :padding="false">
                @forelse ($segments as $s)
                    <button type="button" wire:click="load({{ $s['model']->id }})" wire:key="seg-{{ $s['model']->id }}"
                            @class(['flex w-full items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 text-left text-sm last:border-0 dark:border-slate-800', 'bg-brand-50 dark:bg-brand-400/10' => $segmentId === $s['model']->id, 'hover:bg-slate-50 dark:hover:bg-slate-800/40' => $segmentId !== $s['model']->id])>
                        <span class="min-w-0"><span class="block truncate font-medium">{{ $s['model']->name }}</span>@if ($s['model']->campaigns_count)<span class="text-xs text-slate-500">{{ $s['model']->campaigns_count }} campaign(s)</span>@endif</span>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold tabular-nums dark:bg-slate-800">{{ $s['count'] }}</span>
                    </button>
                @empty
                    <x-empty-state icon="funnel" title="No segments yet" description="Build your first audience on the right." />
                @endforelse
            </x-card>
        </aside>

        <div class="min-w-0 space-y-6 xl:col-span-3">
            {{-- Live result bar --}}
            <div class="card flex flex-col gap-4 p-5 sm:flex-row sm:items-center">
                <div class="flex items-center gap-4">
                    <div class="relative flex size-16 items-center justify-center rounded-2xl bg-brand-700 text-white">
                        <span class="text-2xl font-bold tabular-nums" wire:loading.class="opacity-40">{{ $count }}</span>
                    </div>
                    <div>
                        <p class="font-semibold">matching customers <span class="font-normal text-slate-500">of {{ $total }}</span></p>
                        <p class="text-sm text-slate-500"><span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ $consented }}</span> can be contacted · {{ $count - $consented }} excluded (no consent)</p>
                    </div>
                </div>
                <p class="flex-1 text-sm text-slate-600 sm:text-right dark:text-slate-400">{{ $summary }}</p>
            </div>

            <x-card title="Rules" subtitle="Rules combine with AND · options within a rule combine with OR">
                <div class="space-y-5">
                    @foreach ([
                        'lifecycle_stage' => ['Lifecycle stage', LifecycleStage::cases()],
                        'travel_style' => ['Travel style', TravelStyle::cases()],
                        'type' => ['Customer type', CustomerType::cases()],
                        'budget_band' => ['Budget band', BudgetBand::cases()],
                        'source' => ['Acquisition source', CustomerSource::cases()],
                    ] as $rule => [$label, $cases])
                        <fieldset>
                            <legend class="form-label">{{ $label }}</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($cases as $case)
                                    @php($on = in_array($case->value, $rules[$rule], true))
                                    <button type="button" wire:click="toggle('{{ $rule }}', '{{ $case->value }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                            @class(['inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition', 'border-brand-600 bg-brand-600 text-white dark:bg-brand-500' => $on, 'border-slate-200 hover:border-slate-300 dark:border-slate-700' => ! $on])>
                                        <x-hicon :name="$on ? 'check' : $case->icon()" class="size-3.5" />{{ $case->label() }}
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <fieldset>
                        <legend class="form-label">Tags</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($tags as $t)
                                @php($on = in_array((string) $t->id, $rules['tags'], true))
                                <button type="button" wire:click="toggle('tags', '{{ $t->id }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" @class(['rounded-full border px-3 py-1 text-xs font-medium', 'border-brand-600 bg-brand-600 text-white' => $on, 'border-slate-200 dark:border-slate-700' => ! $on])>{{ $t->name }}</button>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-1 gap-4 border-t border-slate-100 pt-5 sm:grid-cols-2 lg:grid-cols-3 dark:border-slate-800">
                        <x-field label="Marketing consent" for="r-consent">
                            <select id="r-consent" wire:model.live="rules.marketing_consent" class="form-input"><option value="">Any</option><option value="1">Given</option><option value="0">Not given</option></select>
                        </x-field>
                        <x-field label="Country" for="r-country">
                            <select id="r-country" wire:model.live="rules.country" multiple class="form-input h-[42px] focus:h-32">@foreach ($countries as $c)<option value="{{ $c }}">{{ $c }}</option>@endforeach</select>
                        </x-field>
                        <x-field label="Birthday month" for="r-bday" error="rules.birthday_month">
                            <select id="r-bday" wire:model.live="rules.birthday_month" class="form-input"><option value="">Any</option>@foreach (range(1, 12) as $m)<option value="{{ $m }}">{{ \Illuminate\Support\Carbon::create(null, $m)->format('F') }}</option>@endforeach</select>
                        </x-field>
                        <x-field label="At least N trips" for="r-min" error="rules.min_bookings"><input id="r-min" type="number" min="0" wire:model.live.debounce.400ms="rules.min_bookings" class="form-input" placeholder="e.g. 2"></x-field>
                        <x-field label="Lifetime value ≥ (KES)" for="r-ltv" error="rules.min_lifetime_value"><input id="r-ltv" type="number" min="0" step="10000" wire:model.live.debounce.400ms="rules.min_lifetime_value" class="form-input" placeholder="e.g. 500000"></x-field>
                        <x-field label="Travelled in last N months" for="r-tw" error="rules.travelled_within_months"><input id="r-tw" type="number" min="1" wire:model.live.debounce.400ms="rules.travelled_within_months" class="form-input"></x-field>
                        <x-field label="Hasn't travelled for N months" for="r-ntw" error="rules.not_travelled_within_months" hint="Lapsed customers"><input id="r-ntw" type="number" min="1" wire:model.live.debounce.400ms="rules.not_travelled_within_months" class="form-input"></x-field>
                    </div>
                </div>
            </x-card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <x-card title="Preview" subtitle="Top matches by lifetime value" :padding="false">
                    @forelse ($sample as $c)
                        <a href="{{ route('customers.show', $c) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-2.5 text-sm last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                            <span class="min-w-0 flex-1 truncate font-medium">{{ $c->display_name }}</span>
                            @if (! $c->marketing_consent)<x-hicon name="no-symbol" class="size-4 text-slate-400" title="No marketing consent" />@endif
                            <x-badge :enum="$c->lifecycle_stage" size="xs" />
                            <span class="w-24 text-right text-xs tabular-nums text-slate-500">{{ $c->ltv ? money($c->ltv, compact: true) : '—' }}</span>
                        </a>
                    @empty
                        <x-empty-state icon="magnifying-glass" title="No one matches yet" description="Loosen a rule or two." class="!py-8" />
                    @endforelse
                </x-card>
                <x-card title="Save segment">
                    <form wire:submit="save" class="space-y-3">
                        <x-field label="Name" for="s-name" error="name" required><input id="s-name" wire:model="name" class="form-input" placeholder="e.g. Safari lovers who haven't travelled this year"></x-field>
                        <x-field label="Description" for="s-desc"><input id="s-desc" wire:model="description" class="form-input"></x-field>
                        <details class="text-xs text-slate-500"><summary class="cursor-pointer">Rule JSON (stored)</summary><pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-slate-100">{{ $json }}</pre></details>
                        <div class="flex items-center gap-2">
                            @can('marketing.manage')<x-button type="submit" icon="check" loading="save">{{ $segmentId ? 'Update segment' : 'Save segment' }}</x-button>@endcan
                            @if ($segmentId)
                                <x-button variant="secondary" icon="megaphone" :href="route('campaigns.index', ['segment' => $segmentId, 'new' => 1])" wire:navigate>Use in campaign</x-button>
                                @can('marketing.manage')<button type="button" wire:click="delete" wire:confirm="Delete this segment?" class="ml-auto text-sm text-rose-700 hover:underline dark:text-rose-400">Delete</button>@endcan
                            @endif
                        </div>
                    </form>
                </x-card>
            </div>
        </div>
    </div>
</div>
