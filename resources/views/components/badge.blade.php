@props(['enum' => null, 'color' => null, 'icon' => null, 'label' => null, 'size' => 'sm'])
@php
    // Status is always colour + icon + text, never colour alone (WCAG 1.4.1).
    // Tint pairs: a soft background with a deep foreground from the same family.
    $color = $color ?? $enum?->color() ?? 'slate';
    $icon = $icon ?? $enum?->icon();
    $label = $label ?? $enum?->label() ?? $slot;
    $palette = [
        'slate' => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300',
        'teal' => 'bg-brand-50 text-brand-800 dark:bg-brand-400/15 dark:text-brand-200',
        'emerald' => 'bg-[#E8F5E9] text-[#2E7D32] dark:bg-emerald-400/15 dark:text-emerald-300',
        'sky' => 'bg-[#E3F2FD] text-[#1565C0] dark:bg-sky-400/15 dark:text-sky-300',
        'indigo' => 'bg-[#E8EAF6] text-[#303F9F] dark:bg-indigo-400/15 dark:text-indigo-300',
        'violet' => 'bg-[#EDE7F6] text-[#4527A0] dark:bg-violet-400/15 dark:text-violet-300',
        'amber' => 'bg-[#FFF3E0] text-[#B34700] dark:bg-amber-400/15 dark:text-amber-300',
        'rose' => 'bg-[#FFEBEE] text-[#B71C1C] dark:bg-rose-400/15 dark:text-rose-300',
    ][$color] ?? '';
    $sizes = $size === 'xs' ? 'px-2 py-[2px] text-[10px] gap-1' : 'px-2.5 py-[3px] text-[10.5px] gap-1';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center whitespace-nowrap rounded-full font-bold $palette $sizes"]) }}>
    @if ($icon)<x-hicon :name="$icon" class="size-3.5" />@endif
    {{ $label }}
</span>
