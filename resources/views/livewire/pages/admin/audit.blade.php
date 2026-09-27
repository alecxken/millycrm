<?php

use App\Models\User;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

new #[Title('Audit trail')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $user = '';

    #[Url]
    public string $model = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        return [
            'activities' => Activity::query()->with(['causer', 'subject' => fn ($m) => method_exists($m->getModel(), 'bootSoftDeletes') ? $m->withTrashed() : $m])
                ->when($this->user, fn ($q) => $q->where('causer_type', User::class)->where('causer_id', $this->user))
                ->when($this->model, fn ($q) => $q->where('subject_type', $this->model))
                ->latest()->paginate(20),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'models' => Activity::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type')->mapWithKeys(fn ($t) => [$t => class_basename($t)]),
        ];
    }
}; ?>

<div>
    <x-page-header title="Audit trail" subtitle="Who changed what, and when — accountability built in. Passport numbers are never written to the log." />

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <select wire:model.live="user" class="form-input sm:w-56" aria-label="Filter by user"><option value="">All staff</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
        <select wire:model.live="model" class="form-input sm:w-56" aria-label="Filter by record type"><option value="">All record types</option>@foreach ($models as $class => $label)<option value="{{ $class }}">{{ Str::headline($label) }}</option>@endforeach</select>
    </div>

    <div class="card overflow-hidden" wire:loading.class="opacity-60">
        @if ($activities->isEmpty())
            <x-empty-state icon="finger-print" title="No activity recorded" description="Changes to customers, enquiries, bookings and settings will appear here." />
        @else
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($activities as $a)
                    @php($changes = $a->properties['attributes'] ?? [])
                    @php($old = $a->properties['old'] ?? [])
                    <li class="flex gap-3 px-4 py-3" wire:key="a-{{ $a->id }}">
                        @if ($a->causer)<x-avatar :user="$a->causer" size="sm" />@else<x-avatar initials="SY" color="slate" size="sm" title="System" />@endif
                        <div class="min-w-0 flex-1">
                            <p class="text-sm">
                                <span class="font-semibold">{{ $a->causer?->name ?? 'System' }}</span>
                                {{ $a->description }}
                                @if ($a->subject_type)
                                    <span class="text-slate-500">{{ Str::headline(class_basename($a->subject_type)) }}</span>
                                    <span class="font-medium">{{ $a->subject?->reference ?? $a->subject?->display_name ?? $a->subject?->name ?? '#'.$a->subject_id }}</span>
                                @endif
                            </p>
                            @if ($changes)
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    @foreach ($changes as $field => $value)
                                        <span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $field }}: @if (array_key_exists($field, $old))<span class="text-rose-600 line-through dark:text-rose-400">{{ is_scalar($old[$field]) || $old[$field] === null ? Str::limit((string) ($old[$field] ?? '∅'), 30) : 'json' }}</span> → @endif<span class="text-emerald-700 dark:text-emerald-400">{{ is_scalar($value) || $value === null ? Str::limit((string) ($value ?? '∅'), 30) : 'json' }}</span></span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <time class="shrink-0 text-xs text-slate-500" title="{{ fdate($a->created_at, true) }}">{{ $a->created_at->diffForHumans() }}</time>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $activities->links() }}</div>
        @endif
    </div>
</div>
