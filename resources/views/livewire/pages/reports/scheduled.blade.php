<?php

use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use App\Models\ScheduledReport;
use App\Services\ReportService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Scheduled reports')] class extends Component
{
    public ?string $panel = null;

    public array $form = ['name' => '', 'report_type' => 'sales_summary', 'frequency' => 'weekly', 'recipients' => ''];

    public ?string $output = null;

    public ?string $outputTitle = null;

    public function create(): void
    {
        $this->form = ['name' => '', 'report_type' => 'sales_summary', 'frequency' => 'weekly', 'recipients' => auth()->user()->email];
        $this->resetValidation();
        $this->panel = 'new';
    }

    public function save(): void
    {
        $this->authorize('create', ScheduledReport::class);
        $data = $this->validate([
            'form.name' => 'required|string|max:120',
            'form.report_type' => ['required', Rule::enum(ReportType::class), 'not_in:ad_hoc'],
            'form.frequency' => ['required', Rule::enum(ReportFrequency::class)],
            'form.recipients' => 'required|string|max:500',
        ], attributes: ['form.name' => 'name', 'form.recipients' => 'recipients'])['form'];

        $emails = collect(preg_split('/[\s,;]+/', $data['recipients']))->filter()->values();
        if ($emails->contains(fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL))) {
            $this->addError('form.recipients', 'Please enter valid email addresses, separated by commas.');

            return;
        }

        ScheduledReport::create([...$data, 'recipients' => $emails->all(), 'created_by' => auth()->id()]);
        $this->panel = null;
        $this->dispatch('toast', message: 'Scheduled report created.');
    }

    public function runNow(int $id, ReportService $reports): void
    {
        $report = ScheduledReport::findOrFail($id);
        $this->authorize('update', $report);
        $this->output = $reports->deliver($report);
        $this->outputTitle = $report->name;
        $this->panel = 'output';
        $this->dispatch('toast', message: 'Report generated and emailed to '.count($report->recipients).' recipient(s) (log driver).');
    }

    public function view(int $id): void
    {
        $report = ScheduledReport::findOrFail($id);
        $this->output = $report->last_output;
        $this->outputTitle = $report->name;
        $this->panel = 'output';
    }

    public function delete(int $id): void
    {
        $report = ScheduledReport::findOrFail($id);
        $this->authorize('delete', $report);
        $report->delete();
        $this->dispatch('toast', message: 'Scheduled report removed.');
    }

    public function with(): array
    {
        return ['reports' => ScheduledReport::with('creator:id,name,avatar_color')->orderBy('name')->get()];
    }
}; ?>

<div>
    <x-page-header title="Scheduled reports" subtitle="Reports that run on their own — daily, weekly or monthly — and land in people's inboxes.">
        <x-slot:actions><x-button icon="plus" wire:click="create">New scheduled report</x-button></x-slot:actions>
    </x-page-header>

    <div class="mb-6 flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-100">
        <x-hicon name="information-circle" class="size-5" />
        <p>The scheduler runs <code class="rounded bg-white/60 px-1 dark:bg-black/20">php artisan crm:run-scheduled-reports</code> every hour; each report runs when its frequency is due. Emails use the <em>log</em> driver in this prototype, so they are written to <code class="rounded bg-white/60 px-1 dark:bg-black/20">storage/logs/laravel.log</code>.</p>
    </div>

    <div class="card overflow-hidden">
        @if ($reports->isEmpty())
            <x-empty-state icon="calendar-days" title="No scheduled reports" description="Create one here, or save any ad-hoc report as a schedule.">
                <x-button icon="plus" wire:click="create">Schedule your first report →</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Report</th><th>Frequency</th><th>Recipients</th><th>Last run</th><th>Next due</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($reports as $r)
                            <tr wire:key="sr-{{ $r->id }}">
                                <td><p class="font-semibold text-slate-900 dark:text-white">{{ $r->name }}</p><div class="mt-1"><x-badge :enum="$r->report_type" size="xs" /></div></td>
                                <td><x-badge :enum="$r->frequency" /></td>
                                <td class="max-w-56 text-xs">{{ implode(', ', $r->recipients) }}</td>
                                <td class="text-xs">{{ $r->last_run_at ? $r->last_run_at->diffForHumans() : 'Never' }}</td>
                                <td class="text-xs">@if ($r->isDue())<x-badge color="amber" icon="clock" label="Due now" size="xs" />@else{{ fdate(match ($r->frequency->value) { 'daily' => $r->last_run_at->copy()->addDay(), 'weekly' => $r->last_run_at->copy()->addWeek(), default => $r->last_run_at->copy()->addMonth() }) }}@endif</td>
                                <td class="whitespace-nowrap text-right">
                                    @if ($r->last_output)<x-button size="sm" variant="ghost" icon="eye" wire:click="view({{ $r->id }})">Last output</x-button>@endif
                                    <x-button size="sm" variant="secondary" icon="play" wire:click="runNow({{ $r->id }})">Run now</x-button>
                                    @can('delete', $r)<button type="button" wire:click="delete({{ $r->id }})" wire:confirm="Delete “{{ $r->name }}”?" class="ml-1 rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600"><span class="sr-only">Delete</span><x-hicon name="trash" class="size-4" /></button>@endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <x-slide-over name="new" title="New scheduled report" width="max-w-md">
        <form wire:submit="save" id="sr-form" class="space-y-4">
            <x-field label="Name" for="sr-name" error="form.name" required><input id="sr-name" wire:model="form.name" class="form-input" placeholder="e.g. Monday sales summary"></x-field>
            <x-field label="Report" for="sr-type">
                <select id="sr-type" wire:model="form.report_type" class="form-input">@foreach (ReportType::cases() as $t)@continue($t === ReportType::AdHoc)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
            </x-field>
            <x-field label="Frequency" for="sr-freq"><select id="sr-freq" wire:model="form.frequency" class="form-input">@foreach (ReportFrequency::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
            <x-field label="Recipients" for="sr-rec" error="form.recipients" hint="Comma-separated email addresses"><textarea id="sr-rec" wire:model="form.recipients" rows="2" class="form-input"></textarea></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="sr-form" loading="save">Create</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="output" :title="$outputTitle ?? 'Report'" description="Exactly what recipients receive" width="max-w-2xl">
        <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 font-mono text-xs leading-relaxed text-slate-100">{{ $output }}</pre>
    </x-slide-over>
</div>
