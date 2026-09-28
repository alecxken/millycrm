@props(['label', 'value', 'icon' => null, 'hint' => null, 'trend' => null, 'tone' => 'teal', 'href' => null])
@php
    $tones = [
        'teal' => 'bg-brand-50 text-brand-700 dark:bg-brand-400/15 dark:text-brand-200',
        'amber' => 'bg-[#FFF3E0] text-[#B36000] dark:bg-amber-400/15 dark:text-amber-300',
        'violet' => 'bg-[#EDE7F6] text-[#6B3FA0] dark:bg-violet-400/15 dark:text-violet-300',
        'sky' => 'bg-[#E3F2FD] text-[#0277BD] dark:bg-sky-400/15 dark:text-sky-300',
        'rose' => 'bg-[#FFEBEE] text-[#B71C1C] dark:bg-rose-400/15 dark:text-rose-300',
        'emerald' => 'bg-[#F1F8E9] text-[#558B2F] dark:bg-emerald-400/15 dark:text-emerald-300',
    ][$tone] ?? 'bg-brand-50 text-brand-700';
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'card group flex min-w-0 items-center gap-4 p-5 transition-all duration-150'.($href ? ' hover:-translate-y-0.5 hover:shadow-[var(--shadow-lift)]' : '')]) }}>
    @if ($icon)
        <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl {{ $tones }}"><x-hicon :name="$icon" class="size-6" /></span>
    @endif
    <div class="min-w-0 flex-1">
        <p class="truncate text-2xl leading-none font-bold tracking-[-0.02em] text-brand-900 dark:text-white">{{ $value }}</p>
        <p class="mt-1.5 text-[11.5px] font-semibold text-slate-500 dark:text-slate-400">{{ $label }}</p>
        @if ($trend !== null)
            <p @class(['mt-0.5 inline-flex items-center gap-1 text-[10.5px] font-bold', 'text-[#2E7D32] dark:text-emerald-400' => $trend >= 0, 'text-[#B71C1C] dark:text-rose-400' => $trend < 0])>
                <x-hicon :name="$trend >= 0 ? 'arrow-trending-up' : 'arrow-trending-down'" class="size-3.5" />
                {{ $trend >= 0 ? '+' : '' }}{{ $trend }}% on last month
            </p>
        @elseif ($hint)
            <p class="mt-0.5 text-[10.5px] text-slate-500 dark:text-slate-400">{{ $hint }}</p>
        @endif
    </div>
    @if ($href)<x-hicon name="arrow-right" class="size-4 -translate-x-1 text-slate-300 opacity-0 transition group-hover:translate-x-0 group-hover:text-brand-600 group-hover:opacity-100" />@endif
</{{ $tag }}>
