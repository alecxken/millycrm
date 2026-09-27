@props(['title' => null, 'subtitle' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'card min-w-0']) }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5 dark:border-slate-800">
            <div class="min-w-0">
                @if ($title)<h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>@endif
                @if ($subtitle)<p class="text-xs text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['p-4 sm:p-5' => $padding])>{{ $slot }}</div>
</section>
