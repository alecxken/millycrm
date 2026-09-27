@props(['href', 'icon', 'active' => false, 'badge' => null])
<a href="{{ $href }}" wire:navigate
   @class([
       'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
       'bg-brand-700 text-white shadow-sm dark:bg-brand-600' => $active,
       'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' => ! $active,
   ])
   @if($active) aria-current="page" @endif
   :class="collapsed && 'lg:justify-center lg:px-2'"
   x-bind:title="collapsed ? @js(trim($slot)) : null">
    <x-hicon :name="$icon" @class(['size-5', 'text-white' => $active, 'text-slate-400 group-hover:text-slate-600 dark:text-slate-500 dark:group-hover:text-slate-300' => ! $active]) />
    <span class="truncate" :class="collapsed && 'lg:sr-only'">{{ $slot }}</span>
    @if ($badge)
        <span :class="collapsed && 'lg:hidden'" @class(['ml-auto rounded-full px-2 py-0.5 text-xs font-semibold', 'bg-white/20 text-white' => $active, 'bg-sand-500/15 text-amber-800 dark:text-amber-300' => ! $active])>{{ $badge }}</span>
    @endif
</a>
