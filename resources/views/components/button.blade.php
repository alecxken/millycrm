@props(['variant' => 'primary', 'size' => 'md', 'icon' => null, 'href' => null, 'loading' => null])
@php
    $variants = [
        'primary' => 'bg-brand-700 text-white shadow-sm hover:bg-brand-800 dark:bg-brand-600 dark:hover:bg-brand-500',
        'accent' => 'bg-sand-500 text-amber-950 shadow-sm hover:bg-sand-400',
        'secondary' => 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 shadow-sm hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-700 dark:hover:bg-slate-800',
        'ghost' => 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
        'danger' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700',
    ][$variant];
    $sizes = ['sm' => 'px-2.5 py-1.5 text-xs gap-1.5', 'md' => 'px-3.5 py-2 text-sm gap-2', 'lg' => 'px-4 py-2.5 text-sm gap-2'][$size];
    $classes = "inline-flex items-center justify-center rounded-lg font-semibold transition disabled:cursor-not-allowed disabled:opacity-60 $variants $sizes";
    $target = $loading ?? $attributes->whereStartsWith('wire:click')->first();
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-hicon :name="$icon" class="size-4" />@endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }} @if($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif>
        @if ($target)
            <svg wire:loading wire:target="{{ $target }}" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            @if ($icon)<x-hicon :name="$icon" class="size-4" wire:loading.remove wire:target="{{ $target }}" />@endif
        @elseif ($icon)
            <x-hicon :name="$icon" class="size-4" />
        @endif
        {{ $slot }}
    </button>
@endif
