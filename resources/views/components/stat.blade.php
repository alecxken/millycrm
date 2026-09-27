@props(['label', 'value', 'icon' => null, 'hint' => null, 'trend' => null, 'tone' => 'teal', 'href' => null])
@php
    $tones = [
        'teal' => 'bg-teal-50 text-teal-700 dark:bg-teal-400/10 dark:text-teal-300',
        'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',
        'violet' => 'bg-violet-50 text-violet-700 dark:bg-violet-400/10 dark:text-violet-300',
        'sky' => 'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
        'rose' => 'bg-rose-50 text-rose-700 dark:bg-rose-400/10 dark:text-rose-300',
        'emerald' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
    ][$tone];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'card flex min-w-0 items-start gap-4 p-4 sm:p-5'.($href ? ' transition hover:border-brand-300 hover:shadow-md dark:hover:border-brand-700' : '')]) }}>
    @if ($icon)
        <span class="rounded-xl p-2.5 {{ $tones }}"><x-hicon :name="$icon" class="size-5" /></span>
    @endif
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
        <p class="mt-1 truncate text-2xl font-bold tracking-tight text-slate-900 dark:text-white">{{ $value }}</p>
        @if ($trend !== null)
            <p @class(['mt-1 inline-flex items-center gap-1 text-xs font-medium', 'text-emerald-700 dark:text-emerald-400' => $trend >= 0, 'text-rose-700 dark:text-rose-400' => $trend < 0])>
                <x-hicon :name="$trend >= 0 ? 'arrow-trending-up' : 'arrow-trending-down'" class="size-4" />
                {{ $trend >= 0 ? '+' : '' }}{{ $trend }}% vs last month
            </p>
        @elseif ($hint)
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
