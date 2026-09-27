@props(['enum' => null, 'color' => null, 'icon' => null, 'label' => null, 'size' => 'sm'])
@php
    // Status is always colour + icon + text, never colour alone (WCAG 1.4.1).
    $color = $color ?? $enum?->color() ?? 'slate';
    $icon = $icon ?? $enum?->icon();
    $label = $label ?? $enum?->label() ?? $slot;
    $palette = [
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-600/20 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-400/20',
        'teal' => 'bg-teal-50 text-teal-800 ring-teal-600/20 dark:bg-teal-400/10 dark:text-teal-300 dark:ring-teal-400/30',
        'emerald' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/30',
        'sky' => 'bg-sky-50 text-sky-800 ring-sky-600/20 dark:bg-sky-400/10 dark:text-sky-300 dark:ring-sky-400/30',
        'indigo' => 'bg-indigo-50 text-indigo-800 ring-indigo-600/20 dark:bg-indigo-400/10 dark:text-indigo-300 dark:ring-indigo-400/30',
        'violet' => 'bg-violet-50 text-violet-800 ring-violet-600/20 dark:bg-violet-400/10 dark:text-violet-300 dark:ring-violet-400/30',
        'amber' => 'bg-amber-50 text-amber-800 ring-amber-600/30 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30',
        'rose' => 'bg-rose-50 text-rose-800 ring-rose-600/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/30',
    ][$color] ?? '';
    $sizes = $size === 'xs' ? 'px-1.5 py-0.5 text-[11px] gap-1' : 'px-2 py-0.5 text-xs gap-1';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full font-medium ring-1 ring-inset $palette $sizes"]) }}>
    @if ($icon)<x-hicon :name="$icon" class="size-3.5" />@endif
    {{ $label }}
</span>
