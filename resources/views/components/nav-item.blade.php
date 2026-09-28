@props(['href', 'icon', 'active' => false, 'badge' => null])
<a href="{{ $href }}" wire:navigate
   @class([
       'group relative flex items-center gap-3 rounded-[14px] px-3.5 py-2.5 transition-colors duration-150',
       'bg-brand-50 dark:bg-brand-400/10' => $active,
       'hover:bg-slate-100/70 dark:hover:bg-slate-800/60' => ! $active,
   ])
   @if($active) aria-current="page" @endif
   :class="collapsed && 'lg:justify-center lg:px-2'"
   x-bind:title="collapsed ? @js(trim($slot)) : null">
    @if ($active)
        {{-- The "you are here" rail, bleeding into the nav gutter so it sits on the edge. --}}
        <span class="absolute top-[9px] bottom-[9px] -left-3.5 w-[3px] rounded-r-[3px] bg-brand-700 dark:bg-brand-300" aria-hidden="true"></span>
    @endif
    <x-hicon :name="$icon" @class(['size-[19px] transition-colors', 'text-brand-700 dark:text-brand-300' => $active, 'text-slate-400 group-hover:text-brand-700 dark:text-slate-500 dark:group-hover:text-brand-300' => ! $active]) />
    <span :class="collapsed && 'lg:sr-only'" @class(['truncate text-[13.5px] transition-colors', 'font-bold text-brand-900 dark:text-white' => $active, 'font-semibold text-slate-600 group-hover:text-slate-700 dark:text-slate-400 dark:group-hover:text-slate-200' => ! $active])>{{ $slot }}</span>
    @if ($badge)
        <span :class="collapsed && 'lg:hidden'" class="ml-auto flex h-[19px] min-w-[19px] items-center justify-center rounded-full bg-sand-500 px-1.5 text-[9.5px] font-extrabold text-[var(--accent-ink)]">{{ $badge }}</span>
    @endif
</a>
