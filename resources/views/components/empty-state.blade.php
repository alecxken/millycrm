@props(['icon' => 'sparkles', 'title', 'description' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-12 text-center']) }}>
    <span class="mb-4 rounded-2xl bg-brand-50 p-3 text-brand-700 dark:bg-brand-400/10 dark:text-brand-300">
        <x-hicon :name="$icon" class="size-7" />
    </span>
    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
    @if ($description)<p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
