@props(['variant' => 'primary', 'size' => 'md', 'icon' => null, 'href' => null, 'loading' => null, 'pill' => false])
@php
    // Coloured buttons carry a soft glow in their own hue; that's what makes them feel raised.
    $variants = [
        'primary' => 'bg-brand-700 text-[var(--brand-ink)] shadow-[0_4px_16px_color-mix(in_srgb,var(--brand)_30%,transparent)] hover:bg-brand-800',
        'accent' => 'bg-sand-500 text-[var(--accent-ink)] shadow-[0_4px_16px_color-mix(in_srgb,var(--accent)_32%,transparent)] hover:brightness-95',
        'secondary' => 'bg-white text-brand-700 border-[1.5px] border-slate-200 hover:border-brand-600 hover:bg-[var(--hover-wash)] dark:bg-slate-900 dark:text-brand-300 dark:border-slate-700 dark:hover:border-brand-400',
        'ghost' => 'text-slate-600 hover:bg-[var(--hover-wash)] hover:text-brand-800 dark:text-slate-300 dark:hover:text-white',
        'danger' => 'bg-rose-600 text-white shadow-[0_4px_16px_rgba(225,29,72,.28)] hover:brightness-95',
    ][$variant];
    $sizes = ['sm' => 'px-3.5 py-2 text-xs gap-1.5', 'md' => 'px-4.5 py-2.5 text-sm gap-2', 'lg' => 'px-5 py-3 text-sm gap-2'][$size];
    $shape = $pill ? 'rounded-full' : ($size === 'sm' ? 'rounded-lg' : 'rounded-xl');
    $classes = "inline-flex items-center justify-center font-bold whitespace-nowrap transition-all duration-150 hover:-translate-y-px active:translate-y-0 active:scale-[.98] disabled:cursor-not-allowed disabled:opacity-45 disabled:hover:translate-y-0 $shape $variants $sizes";
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
