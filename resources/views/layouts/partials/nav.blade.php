@php($nav = app(\App\Support\Navigation::class)->for(auth()->user()))
<div class="space-y-6">
    @foreach ($nav as $section => $items)
        <div>
            @if ($section)
                <p class="mb-1 px-3 text-[11px] font-semibold tracking-wider text-slate-400 uppercase dark:text-slate-500" :class="collapsed && 'lg:sr-only'">{{ $section }}</p>
            @endif
            <div class="space-y-0.5">
                @foreach ($items as $item)
                    <x-nav-item :href="route($item['route'])" :icon="$item['icon']" :active="request()->routeIs(...$item['active'])" :badge="$item['badge'] ?? null">{{ $item['label'] }}</x-nav-item>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
