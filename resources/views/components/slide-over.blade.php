{{--
    Slide-over panel driven by the Livewire `$panel` property, so quick actions
    never cause a page change. Usage: <x-slide-over name="log-call" title="Log a call">...</x-slide-over>
--}}
@props(['name', 'title', 'description' => null, 'width' => 'max-w-lg'])
<div x-data x-cloak x-show="$wire.panel === '{{ $name }}'" x-on:keydown.escape.window="if ($wire.panel === '{{ $name }}') $wire.panel = null"
     class="relative z-50" role="dialog" aria-modal="true" aria-labelledby="panel-{{ $name }}-title">
    <div x-show="$wire.panel === '{{ $name }}'" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-[2px]" x-on:click="$wire.panel = null"></div>
    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-6 sm:pl-10">
        <div x-show="$wire.panel === '{{ $name }}'"
             x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             x-trap.inert.noscroll="$wire.panel === '{{ $name }}'"
             class="pointer-events-auto w-screen {{ $width }}">
            <div class="flex h-full flex-col bg-white shadow-2xl dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 id="panel-{{ $name }}-title" class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                        @if ($description)<p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>@endif
                    </div>
                    <button type="button" x-on:click="$wire.panel = null" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                        <span class="sr-only">Close panel</span>
                        <x-hicon name="x-mark" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-5 py-5">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-3 dark:border-slate-800">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </div>
</div>
