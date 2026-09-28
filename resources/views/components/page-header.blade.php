@props(['title', 'subtitle' => null, 'eyebrow' => null])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        @if ($eyebrow)<p class="eyebrow mb-1">{{ $eyebrow }}</p>@endif
        @isset($eyebrowSlot)<div class="mb-1">{{ $eyebrowSlot }}</div>@endisset
        <h1 class="truncate text-[27px] leading-tight font-bold tracking-[-0.01em] text-brand-900 sm:text-[31px] dark:text-white">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-1 text-[13.5px] font-light text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 sm:pt-1.5">{{ $actions }}</div>
    @endisset
</div>
