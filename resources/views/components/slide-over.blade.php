{{--
    Slide-over panel driven by the Livewire `$panel` property, so quick actions
    never cause a page change. Usage: <x-slide-over name="log-call" title="Log a call">...</x-slide-over>
--}}
@props(['name', 'title', 'description' => null, 'width' => 'max-w-lg'])
<div x-data x-cloak x-show="$wire.panel === '{{ $name }}'" x-on:keydown.escape.window="if ($wire.panel === '{{ $name }}') $wire.panel = null"
     class="relative z-50" role="dialog" aria-modal="true" aria-labelledby="panel-{{ $name }}-title">
    <div x-show="$wire.panel === '{{ $name }}'" x-transition.opacity class="fixed inset-0 bg-[rgba(0,32,48,.5)] backdrop-blur-[2px]" x-on:click="$wire.panel = null"></div>
    <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-6 sm:pl-10">
        <div x-show="$wire.panel === '{{ $name }}'"
             x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transform transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
             x-trap.inert.noscroll="$wire.panel === '{{ $name }}'"
             class="pointer-events-auto w-screen {{ $width }}">
            <div class="flex h-full flex-col overflow-hidden bg-white shadow-[var(--shadow-lift)] sm:rounded-l-3xl dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4 bg-gradient-to-br from-brand-900 to-brand-700 px-6 py-5 text-white">
                    <div class="min-w-0">
                        <h2 id="panel-{{ $name }}-title" class="text-[15px] font-bold">{{ $title }}</h2>
                        @if ($description)<p class="mt-0.5 text-[12px] font-normal text-white/75">{{ $description }}</p>@endif
                    </div>
                    <button type="button" x-on:click="$wire.panel = null" class="relative rounded-[10px] p-1.5 text-white/75 transition hover:bg-white/15 hover:text-white">
                        <span class="sr-only">Close panel</span>
                        <x-hicon name="x-mark" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-6 py-5">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 px-6 py-4 sm:flex-row sm:justify-end dark:border-slate-800">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </div>
</div>
