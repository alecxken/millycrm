@php($nav = app(\App\Support\Navigation::class)->for(auth()->user()))
<div>
    @foreach ($nav as $section => $items)
        @if ($section)
            <p class="px-3.5 pt-5 pb-1.5 text-[9.5px] font-bold tracking-[0.16em] text-slate-500 uppercase dark:text-slate-500" :class="collapsed && 'lg:sr-only'">{{ $section }}</p>
        @endif
        <div class="flex flex-col gap-0.5">
            @foreach ($items as $item)
                <x-nav-item :href="route($item['route'])" :icon="$item['icon']" :active="request()->routeIs(...$item['active'])" :badge="$item['badge'] ?? null">{{ $item['label'] }}</x-nav-item>
            @endforeach
        </div>
    @endforeach
</div>
