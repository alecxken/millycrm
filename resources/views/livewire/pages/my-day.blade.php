<?php

use App\Enums\BookingStatus;
use App\Enums\Priority;
use App\Enums\TaskType;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('My Day')] class extends Component
{
    #[Url]
    public bool $team = false;

    public string $quickTask = '';

    public function scopeUser(): ?int
    {
        return $this->team && auth()->user()->isManagerOrAbove() ? null : auth()->id();
    }

    public function complete(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('update', $task);
        $task->update(['completed_at' => now()]);
        $this->dispatch('toast', message: 'Done! '.$task->title, undo: ['id' => $this->getId(), 'method' => 'reopen', 'args' => [$id]]);
    }

    public function reopen(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('update', $task);
        $task->update(['completed_at' => null]);
    }

    public function snooze(int $id): void
    {
        $task = Task::findOrFail($id);
        $this->authorize('update', $task);
        $task->update(['due_at' => now()->addDay()->setTime(9, 0)]);
        $this->dispatch('toast', message: 'Snoozed until tomorrow 09:00.');
    }

    public function addQuickTask(): void
    {
        $this->validate(['quickTask' => 'required|string|max:150'], attributes: ['quickTask' => 'task']);
        Task::create(['title' => $this->quickTask, 'assigned_to' => auth()->id(), 'created_by' => auth()->id(), 'type' => TaskType::General, 'priority' => Priority::Normal, 'due_at' => now()->setTime(17, 0)]);
        $this->quickTask = '';
        $this->dispatch('toast', message: 'Added to today.');
    }

    public function with(): array
    {
        $uid = $this->scopeUser();
        $consultantScope = fn ($q, $col = 'assigned_to') => $q->when($uid, fn ($w) => $w->where($col, $uid));
        $customerCols = 'customer:id,first_name,last_name,company_name,type,phone,whatsapp';

        $tasks = $consultantScope(Task::pending())->where('due_at', '<=', now()->endOfDay())
            ->with([$customerCols, 'enquiry:id,reference,destination', 'assignee:id,name,avatar_color'])->orderBy('due_at')->get();

        $driver = DB::connection()->getDriverName();
        $today = now()->format('m-d');
        $birthdayExpr = $driver === 'sqlite' ? "strftime('%m-%d', date_of_birth)" : "DATE_FORMAT(date_of_birth, '%m-%d')";

        return [
            'overdue' => $tasks->filter(fn ($t) => $t->due_at->lt(now()->startOfDay())),
            'today' => $tasks->filter(fn ($t) => $t->due_at->gte(now()->startOfDay())),
            'doneToday' => $consultantScope(Task::query())->whereDate('completed_at', today())->count(),
            'departing' => $consultantScope(Booking::query(), 'consultant_id')->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Travelling])
                ->whereBetween('start_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                ->with($customerCols)->orderBy('start_date')->get(),
            'passports' => $consultantScope(Customer::query())->whereNotNull('passport_expiry')
                ->whereBetween('passport_expiry', [now()->subMonth(), now()->addMonths(6)])->orderBy('passport_expiry')->limit(10)->get(),
            'birthdays' => $consultantScope(Customer::query())->whereNotNull('date_of_birth')->whereRaw("$birthdayExpr = ?", [$today])->get(),
        ];
    }
}; ?>

<div>
    <x-page-header title="My Day" :subtitle="now()->format('l, j F').' — here is what needs you today.'">
        <x-slot:actions>
            @if (auth()->user()->isManagerOrAbove())
                <label class="inline-flex items-center gap-2 text-sm font-medium"><input type="checkbox" wire:model.live="team" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600"> Whole team</label>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Progress strip: gives a sense of momentum --}}
    @php($total = $overdue->count() + $today->count() + $doneToday)
    <div class="card mb-6 flex flex-col gap-4 p-4 sm:flex-row sm:items-center">
        <div class="flex-1">
            <p class="text-sm font-semibold">{{ $doneToday }} of {{ $total }} tasks done today</p>
            <div class="mt-2 h-2 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-brand-600 transition-all" style="width: {{ $total ? round($doneToday / $total * 100) : 100 }}%"></div></div>
        </div>
        <form wire:submit="addQuickTask" class="flex gap-2 sm:w-96">
            <label for="quick-task" class="sr-only">Quick task</label>
            <input id="quick-task" wire:model="quickTask" class="form-input" placeholder="Quick task for today…">
            <x-button type="submit" icon="plus" loading="addQuickTask"><span class="sr-only sm:not-sr-only">Add</span></x-button>
        </form>
    </div>
    @error('quickTask')<p class="-mt-4 mb-4 text-sm text-rose-700">{{ $message }}</p>@enderror

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($overdue->isNotEmpty())
                <x-card :padding="false" class="border-rose-200 dark:border-rose-400/30">
                    <x-slot:title><span class="inline-flex items-center gap-2 text-rose-700 dark:text-rose-400"><x-hicon name="exclamation-triangle" class="size-4" />Overdue follow-ups ({{ $overdue->count() }})</span></x-slot:title>
                    @foreach ($overdue as $task)
                        @include('partials.task-row', ['task' => $task, 'showOwner' => $team])
                    @endforeach
                </x-card>
            @endif

            <x-card :title="'Due today ('.$today->count().')'" :padding="false">
                @forelse ($today as $task)
                    @include('partials.task-row', ['task' => $task, 'showOwner' => $team])
                @empty
                    <x-empty-state icon="sun" title="Nothing else due today" description="Use the time to call a lapsed customer or chase a quiet enquiry.">
                        <x-button variant="secondary" :href="route('pipeline')" wire:navigate icon="view-columns">See quiet enquiries →</x-button>
                    </x-empty-state>
                @endforelse
            </x-card>

            <x-card :title="'Departing in the next 7 days ('.$departing->count().')'" subtitle="Check documents, send e-tickets and wish them well" :padding="false">
                @forelse ($departing as $b)
                    <a href="{{ route('bookings.show', $b) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                        <div class="flex size-11 shrink-0 flex-col items-center justify-center rounded-xl bg-sky-50 text-sky-800 dark:bg-sky-400/10 dark:text-sky-300"><span class="text-[10px] font-semibold uppercase">{{ $b->start_date->format('M') }}</span><span class="text-sm leading-none font-bold">{{ $b->start_date->format('j') }}</span></div>
                        <div class="min-w-0 flex-1"><p class="truncate font-medium">{{ $b->customer->display_name }}</p><p class="text-xs text-slate-500">{{ $b->destination }} · {{ $b->start_date->diffForHumans() }}</p></div>
                        <x-badge :enum="$b->payment_status" size="xs" />
                    </a>
                @empty
                    <x-empty-state icon="paper-airplane" title="No departures this week" class="!py-8" />
                @endforelse
            </x-card>
        </div>

        <aside class="space-y-6">
            <x-card title="Birthdays today 🎂" :padding="false">
                @forelse ($birthdays as $c)
                    <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
                        <x-avatar :initials="$c->initials" color="amber" size="sm" :title="false" />
                        <div class="min-w-0 flex-1"><a href="{{ route('customers.show', $c) }}" wire:navigate class="block truncate text-sm font-medium hover:text-brand-700">{{ $c->display_name }}</a><p class="text-xs text-slate-500">Turns {{ $c->date_of_birth->age }}</p></div>
                        @if ($c->whatsapp)<a href="https://wa.me/{{ preg_replace('/\D/', '', $c->whatsapp) }}?text={{ rawurlencode('Happy birthday '.$c->first_name.'! 🎉 Wishing you a wonderful year of adventures — from all of us at WanderLink Travel.') }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-50 p-2 text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-400/10 dark:text-emerald-300" title="Send WhatsApp wishes"><x-hicon name="chat-bubble-left-right" class="size-4" /><span class="sr-only">WhatsApp {{ $c->first_name }}</span></a>@endif
                    </div>
                @empty
                    <p class="px-4 py-4 text-sm text-slate-500">No birthdays today.</p>
                @endforelse
            </x-card>

            <x-card title="Passports expiring within 6 months" :padding="false">
                @forelse ($passports as $c)
                    <a href="{{ route('customers.show', [$c, 'tab' => 'documents']) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                        <x-hicon name="identification" @class(['size-5', 'text-rose-600' => $c->passport_expiry->lt(now()->addMonths(2)), 'text-amber-600' => $c->passport_expiry->gte(now()->addMonths(2))]) />
                        <div class="min-w-0 flex-1"><p class="truncate text-sm font-medium">{{ $c->display_name }}</p><p class="text-xs text-slate-500">{{ $c->passport_expiry->isPast() ? 'Expired' : 'Expires' }} {{ fdate($c->passport_expiry) }}</p></div>
                    </a>
                @empty
                    <p class="px-4 py-4 text-sm text-slate-500">All passports are comfortably valid.</p>
                @endforelse
            </x-card>
        </aside>
    </div>
</div>
