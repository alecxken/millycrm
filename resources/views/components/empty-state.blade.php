@props(['icon' => 'sparkles', 'title', 'description' => null])
{{-- Quiet, not alarming: an empty list is a state, not an error. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <x-hicon :name="$icon" class="mb-3 size-9 text-slate-300 dark:text-slate-600" />
    <h3 class="text-[13.5px] font-bold text-slate-700 dark:text-white">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-[12.5px] text-slate-500 dark:text-slate-400">{{ $description }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
