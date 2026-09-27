@props(['user' => null, 'initials' => null, 'color' => null, 'size' => 'md', 'title' => null])
@php
    $initials = $initials ?? $user?->initials() ?? '?';
    $color = $color ?? $user?->avatar_color ?? 'slate';
    $title = $title ?? $user?->name;
    $bg = [
        'teal' => 'bg-teal-600', 'amber' => 'bg-amber-500 text-amber-950', 'violet' => 'bg-violet-600', 'sky' => 'bg-sky-600',
        'rose' => 'bg-rose-600', 'indigo' => 'bg-indigo-600', 'emerald' => 'bg-emerald-600', 'slate' => 'bg-slate-500',
    ][$color] ?? 'bg-slate-500';
    $dim = ['xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'md' => 'size-10 text-sm', 'lg' => 'size-14 text-lg', 'xl' => 'size-16 text-xl'][$size];
@endphp
<span title="{{ $title }}" {{ $attributes->merge(['class' => "inline-flex shrink-0 select-none items-center justify-center rounded-full font-semibold text-white $bg $dim"]) }}>
    <span aria-hidden="true">{{ $initials }}</span>
    @if ($title)<span class="sr-only">{{ $title }}</span>@endif
</span>
