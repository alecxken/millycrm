<?php

use App\Enums\BookingStatus;
use App\Enums\CustomerSource;
use App\Enums\CustomerType;
use App\Enums\EnquiryStatus;
use App\Enums\LifecycleStage;
use App\Enums\PaymentStatus;
use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use App\Enums\Role;
use App\Enums\TravelStyle;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Services\ReportService;
use App\Support\ChartPalette;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Report builder')] class extends Component
{
    #[Url]
    public string $entity = 'bookings';

    #[Url(as: 'group')]
    public string $groupBy = 'month';

    #[Url]
    public array $filters = [];

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public ?string $panel = null;

    public array $schedule = ['name' => '', 'frequency' => 'weekly', 'recipients' => ''];

    public function mount(): void
    {
        $this->from = $this->from ?: now()->subYear()->startOfMonth()->format('Y-m-d');
        $this->to = $this->to ?: now()->format('Y-m-d');
    }

    public function updatedEntity(): void
    {
        $this->groupBy = 'month';
        $this->filters = [];
    }

    public function preset(string $name): void
    {
        [$this->entity, $this->groupBy, $this->filters, $from] = match ($name) {
            'revenue-destination' => ['bookings', 'destination', [], now()->subYear()],
            'lost-reasons' => ['enquiries', 'lost_reason', ['status' => 'lost'], now()->subYear()],
            'leads-channel' => ['enquiries', 'channel', [], now()->subMonths(6)],
            'vip-country' => ['customers', 'country', ['lifecycle_stage' => 'vip'], now()->subYears(3)],
            default => ['bookings', 'month', [], now()->subYear()],
        };
        $this->from = $from->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
    }

    public function openSchedule(): void
    {
        $label = ReportService::ENTITIES[$this->entity]['label'].' by '.strtolower(ReportService::ENTITIES[$this->entity]['group_by'][$this->groupBy]);
        $this->schedule = ['name' => $label, 'frequency' => 'weekly', 'recipients' => auth()->user()->email];
        $this->panel = 'schedule';
    }

    public function saveSchedule(): void
    {
        $this->authorize('create', ScheduledReport::class);
        $data = $this->validate([
            'schedule.name' => 'required|string|max:120',
            'schedule.frequency' => ['required', Rule::enum(ReportFrequency::class)],
            'schedule.recipients' => 'required|string|max:500',
        ], attributes: ['schedule.recipients' => 'recipients'])['schedule'];

        $emails = collect(preg_split('/[\s,;]+/', $data['recipients']))->filter()->values();
        if ($emails->contains(fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL))) {
            $this->addError('schedule.recipients', 'Please enter valid email addresses, separated by commas.');

            return;
        }

        ScheduledReport::create([
            'name' => $data['name'], 'report_type' => ReportType::AdHoc, 'frequency' => $data['frequency'], 'recipients' => $emails->all(),
            'parameters' => ['entity' => $this->entity, 'group_by' => $this->groupBy, 'filters' => array_filter($this->filters)], 'created_by' => auth()->id(),
        ]);
        $this->panel = null;
        $this->dispatch('toast', message: 'Saved as a scheduled report. It will run '.strtolower(ReportFrequency::from($data['frequency'])->label()).'.');
    }

    public function filterOptions(): array
    {
        $consultants = User::role(Role::Consultant->value)->orderBy('name')->pluck('name', 'id')->all();

        return match ($this->entity) {
            'customers' => ['lifecycle_stage' => ['Stage', LifecycleStage::options()], 'source' => ['Source', CustomerSource::options()], 'type' => ['Type', CustomerType::options()], 'assigned_to' => ['Consultant', $consultants]],
            'enquiries' => ['status' => ['Status', EnquiryStatus::options()], 'channel' => ['Channel', CustomerSource::options()], 'trip_type' => ['Trip type', TravelStyle::options()], 'assigned_to' => ['Consultant', $consultants]],
            default => ['status' => ['Status', BookingStatus::options()], 'payment_status' => ['Payment', PaymentStatus::options()], 'consultant_id' => ['Consultant', $consultants]],
        };
    }

    public function with(ReportService $reports): array
    {
        $result = $reports->adHoc($this->entity, $this->groupBy, $this->filters, $this->from, $this->to);
        $valueLabel = ['customers' => 'Lifetime value', 'enquiries' => 'Expected value', 'bookings' => 'Revenue'][$this->entity];
        $rows = collect($result['rows'])->take(20);

        $chart = $this->groupBy === 'month'
            ? ChartPalette::line($rows->pluck('value', 'label')->all(), $valueLabel)
            : ChartPalette::bar($rows->pluck('count', 'label')->all(), 'Count', ChartPalette::series($rows->count()));

        return [
            'result' => $result,
            'valueLabel' => $valueLabel,
            'chart' => $chart,
            'filterOptions' => $this->filterOptions(),
            'exportUrl' => route('reports.export', ['entity' => $this->entity, 'group_by' => $this->groupBy, 'filters' => array_filter($this->filters), 'from' => $this->from, 'to' => $this->to]),
        ];
    }
}; ?>

<div>
    <x-page-header title="Ad-hoc report builder" subtitle="Ask a question of the data: pick what to count, how to group it and when.">
        <x-slot:actions>
            <x-button variant="secondary" icon="arrow-down-tray" :href="$exportUrl">Export CSV</x-button>
            <x-button icon="calendar-days" wire:click="openSchedule">Save as scheduled report</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap items-center gap-2 text-sm">
        <span class="text-slate-500">Try:</span>
        @foreach (['revenue-month' => 'Revenue by month', 'revenue-destination' => 'Revenue by destination', 'lost-reasons' => 'Why we lose deals', 'leads-channel' => 'Leads by channel (6m)', 'vip-country' => 'VIPs by country'] as $key => $label)
            <button type="button" wire:click="preset('{{ $key }}')" class="rounded-full border border-slate-200 bg-white px-3 py-1 font-medium hover:border-brand-400 dark:border-slate-700 dark:bg-slate-900">{{ $label }}</button>
        @endforeach
    </div>

    <div class="card mb-6 p-4">
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <x-field label="1. Count" for="rb-entity">
                <select id="rb-entity" wire:model.live="entity" class="form-input">@foreach (ReportService::ENTITIES as $key => $def)<option value="{{ $key }}">{{ $def['label'] }}</option>@endforeach</select>
            </x-field>
            <x-field label="2. Grouped by" for="rb-group">
                <select id="rb-group" wire:model.live="groupBy" class="form-input">@foreach (ReportService::ENTITIES[$entity]['group_by'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
            </x-field>
            <x-field label="3. From" for="rb-from"><input id="rb-from" type="date" wire:model.live="from" class="form-input"></x-field>
            <x-field label="To" for="rb-to"><input id="rb-to" type="date" wire:model.live="to" class="form-input"></x-field>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3 border-t border-slate-100 pt-3 md:grid-cols-4 dark:border-slate-800">
            @foreach ($filterOptions as $key => [$label, $options])
                <x-field :label="'Filter: '.$label" :for="'rb-f-'.$key">
                    <select id="rb-f-{{ $key }}" wire:model.live="filters.{{ $key }}" class="form-input"><option value="">Any</option>@foreach ($options as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
                </x-field>
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-5" wire:loading.class="opacity-60">
        <x-card :title="ReportService::ENTITIES[$entity]['label'].' by '.strtolower(ReportService::ENTITIES[$entity]['group_by'][$groupBy])" :subtitle="fdate(\Illuminate\Support\Carbon::parse($from)).' – '.fdate(\Illuminate\Support\Carbon::parse($to))" class="lg:col-span-3">
            @if (empty($result['rows']))
                <x-empty-state icon="chart-bar" title="No data for these filters" description="Widen the date range or clear a filter." />
            @else
                <x-chart :config="$chart" label="Report chart" height="h-80" />
            @endif
        </x-card>
        <x-card title="Results" :subtitle="number_format($result['total_count']).' records · '.money($result['total_value']).' total'" :padding="false" class="lg:col-span-2">
            <div class="max-h-96 overflow-y-auto">
                <table class="table-base">
                    <thead class="sticky top-0 bg-slate-50 dark:bg-slate-900"><tr><th>{{ ReportService::ENTITIES[$entity]['group_by'][$groupBy] }}</th><th class="text-right">Count</th><th class="text-right">{{ $valueLabel }}</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($result['rows'] as $row)
                            <tr><td class="font-medium">{{ $row['label'] }}</td><td class="text-right tabular-nums">{{ number_format($row['count']) }}</td><td class="text-right tabular-nums">{{ money($row['value'], compact: true) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 font-semibold dark:bg-slate-900"><tr><td class="px-4 py-3">Total</td><td class="px-4 py-3 text-right tabular-nums">{{ number_format($result['total_count']) }}</td><td class="px-4 py-3 text-right tabular-nums">{{ money($result['total_value'], compact: true) }}</td></tr></tfoot>
                </table>
            </div>
        </x-card>
    </div>

    <x-slide-over name="schedule" title="Save as scheduled report" description="We'll regenerate this report and email it automatically." width="max-w-md">
        <form wire:submit="saveSchedule" id="sched-form" class="space-y-4">
            <x-field label="Report name" for="sc-name" error="schedule.name" required><input id="sc-name" wire:model="schedule.name" class="form-input"></x-field>
            <x-field label="Frequency" for="sc-freq"><select id="sc-freq" wire:model="schedule.frequency" class="form-input">@foreach (ReportFrequency::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
            <x-field label="Recipients" for="sc-rec" error="schedule.recipients" hint="Comma-separated email addresses"><textarea id="sc-rec" wire:model="schedule.recipients" rows="2" class="form-input"></textarea></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="sched-form" loading="saveSchedule">Save schedule</x-button></x-slot:footer>
    </x-slide-over>
</div>
