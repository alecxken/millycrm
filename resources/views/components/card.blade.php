@props(['title' => null, 'subtitle' => null, 'padding' => true, 'icon' => null])
<section {{ $attributes->merge(['class' => 'card min-w-0 overflow-hidden']) }}>
    @if ($title || isset($actions))
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 pt-4.5 pb-3.5 sm:px-6 dark:border-slate-800">
            <div class="flex min-w-0 items-start gap-2.5">
                @if ($icon)<x-hicon :name="$icon" class="mt-px size-[17px] text-brand-700 dark:text-brand-300" />@endif
                <div class="min-w-0">
                    @if ($title)<h2 class="text-[14.5px] leading-tight font-bold text-slate-700 dark:text-white">{{ $title }}</h2>@endif
                    @if ($subtitle)<p class="mt-0.5 text-[11.5px] leading-snug text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>@endif
                </div>
            </div>
            @isset($actions)<div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div @class(['p-5 sm:p-6' => $padding])>{{ $slot }}</div>
</section>
