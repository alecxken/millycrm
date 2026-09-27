{{-- A task line with complete (undoable) and snooze. Expects $task and $showOwner. --}}
<div wire:key="task-{{ $task->id }}" class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
    <button type="button" wire:click="complete({{ $task->id }})" class="group mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-slate-300 transition hover:border-brand-600 hover:bg-brand-50 dark:border-slate-600 dark:hover:bg-brand-400/10" aria-label="Mark “{{ $task->title }}” done">
        <x-hicon name="check" class="size-3.5 text-brand-700 opacity-0 group-hover:opacity-100" wire:loading.class="opacity-100" wire:target="complete({{ $task->id }})" />
    </button>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $task->title }}</p>
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
            <span @class(['inline-flex items-center gap-1', 'font-semibold text-rose-700 dark:text-rose-400' => $task->isOverdue()])><x-hicon name="clock" class="size-3.5" />{{ $task->isOverdue() ? $task->due_at->diffForHumans() : $task->due_at->format('H:i') }}</span>
            <x-badge :enum="$task->type" size="xs" />
            @if (in_array($task->priority, [\App\Enums\Priority::High, \App\Enums\Priority::Urgent], true))<x-badge :enum="$task->priority" size="xs" />@endif
            @if ($task->customer)<a href="{{ route('customers.show', $task->customer) }}" wire:navigate class="link">{{ $task->customer->display_name }}</a>@endif
            @if ($showOwner && $task->assignee)<span class="inline-flex items-center gap-1"><x-avatar :user="$task->assignee" size="xs" />{{ $task->assignee->firstName() }}</span>@endif
        </div>
    </div>
    <div class="flex shrink-0 items-center gap-1">
        @if ($task->customer?->phone)<a href="tel:{{ $task->customer->phone }}" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800" title="Call"><x-hicon name="phone" class="size-4" /><span class="sr-only">Call {{ $task->customer->display_name }}</span></a>@endif
        <button type="button" wire:click="snooze({{ $task->id }})" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800" title="Snooze to tomorrow"><x-hicon name="moon" class="size-4" /><span class="sr-only">Snooze</span></button>
    </div>
</div>
