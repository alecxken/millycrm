<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="h-full bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">Skip to content</a>

<div x-data="{ collapsed: $persist(false).as('sidebar-collapsed'), mobileOpen: false }" class="min-h-full">
    {{-- Mobile off-canvas sidebar --}}
    <div x-cloak x-show="mobileOpen" class="relative z-50 lg:hidden" role="dialog" aria-modal="true">
        <div x-show="mobileOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/60" x-on:click="mobileOpen = false"></div>
        <div x-show="mobileOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
             class="fixed inset-y-0 left-0 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl dark:bg-slate-900">
            <div class="flex items-center justify-between px-4 py-4">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                    <x-application-logo class="size-9" />
                    <span class="text-base font-bold">WanderLink <span class="font-medium text-slate-500">CRM</span></span>
                </a>
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="mobileOpen = false">
                    <span class="sr-only">Close menu</span><x-hicon name="x-mark" />
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-3 pb-4" x-data="{ collapsed: false }">
                @include('layouts.partials.nav')
            </div>
        </div>
    </div>

    {{-- Desktop sidebar --}}
    <aside class="hidden lg:fixed lg:inset-y-0 lg:z-40 lg:flex lg:flex-col lg:border-r lg:border-slate-200 lg:bg-white lg:transition-[width] lg:duration-200 dark:lg:border-slate-800 dark:lg:bg-slate-900"
           :class="collapsed ? 'lg:w-[76px]' : 'lg:w-64'">
        <div class="flex h-16 shrink-0 items-center gap-2.5 px-4" :class="collapsed && 'justify-center px-2'">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                <x-application-logo class="size-9" />
                <span x-show="!collapsed" class="text-base font-bold tracking-tight">WanderLink <span class="font-medium text-slate-500 dark:text-slate-400">CRM</span></span>
            </a>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Main">
            @include('layouts.partials.nav')
        </nav>
        <div class="border-t border-slate-200 p-3 dark:border-slate-800">
            <button type="button" x-on:click="collapsed = !collapsed"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    :class="collapsed && 'justify-center px-2'" :aria-expanded="(!collapsed).toString()">
                <x-hicon name="chevron-double-left" class="size-5 transition" x-bind:class="collapsed && 'rotate-180'" />
                <span x-show="!collapsed">Collapse sidebar</span>
                <span x-show="collapsed" class="sr-only">Expand sidebar</span>
            </button>
        </div>
    </aside>

    <div class="transition-[padding] duration-200" :class="collapsed ? 'lg:pl-[76px]' : 'lg:pl-64'">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/85 px-4 backdrop-blur sm:px-6 dark:border-slate-800 dark:bg-slate-900/85">
            <button type="button" class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden dark:text-slate-300 dark:hover:bg-slate-800" x-on:click="mobileOpen = true">
                <span class="sr-only">Open menu</span><x-hicon name="bars-3" class="size-6" />
            </button>

            <button type="button" x-data x-on:click="$dispatch('open-command-palette')"
                    class="flex h-10 min-w-0 flex-1 items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 text-left text-sm text-slate-500 transition hover:border-slate-300 hover:bg-white sm:max-w-md dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400 dark:hover:bg-slate-800">
                <x-hicon name="magnifying-glass" class="size-5" />
                <span class="flex-1 truncate">Search customers, bookings, phone…</span>
                <kbd class="hidden rounded-md border border-slate-300 bg-white px-1.5 py-0.5 font-sans text-[11px] font-semibold text-slate-500 sm:inline dark:border-slate-600 dark:bg-slate-900 dark:text-slate-400">⌘K</kbd>
            </button>

            <div class="ml-auto flex items-center gap-1 sm:gap-2">
                @can('pipeline.manage')
                    <div class="hidden sm:block"><x-button :href="route('pipeline', ['new' => 1])" wire:navigate icon="plus">New enquiry</x-button></div>
                @endcan

                <button type="button" x-data x-on:click="$store.theme.cycle()" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                        x-bind:title="'Theme: ' + $store.theme.value">
                    <span class="sr-only" x-text="'Theme: ' + $store.theme.value + '. Click to change.'"></span>
                    <span x-show="$store.theme.value === 'light'"><x-hicon name="sun" /></span>
                    <span x-show="$store.theme.value === 'dark'" x-cloak><x-hicon name="moon" /></span>
                    <span x-show="$store.theme.value === 'system'" x-cloak><x-hicon name="computer-desktop" /></span>
                </button>

                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button type="button" class="flex items-center gap-2 rounded-full p-1 pr-2 hover:bg-slate-100 dark:hover:bg-slate-800">
                            <x-avatar :user="auth()->user()" size="sm" />
                            <span class="hidden text-left md:block">
                                <span class="block text-sm font-semibold leading-tight">{{ auth()->user()->name }}</span>
                                <span class="block text-xs leading-tight text-slate-500 dark:text-slate-400">{{ auth()->user()->primaryRole()?->label() }}</span>
                            </span>
                            <x-hicon name="chevron-down" class="hidden size-4 text-slate-400 md:block" />
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-700">
                            <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ auth()->user()->email }}</p>
                        </div>
                        <x-dropdown-link :href="route('profile')" wire:navigate>Profile & password</x-dropdown-link>
                        <x-dropdown-link :href="route('about-system')" wire:navigate>About the system</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-start text-sm leading-5 text-slate-700 transition hover:bg-slate-100 focus:bg-slate-100 focus:outline-none dark:text-slate-300 dark:hover:bg-slate-800 dark:focus:bg-slate-800">Log out</button>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </header>

        <main id="main" class="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
            <div class="mx-auto max-w-7xl">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

<livewire:command-palette />

{{-- Toasts --}}
<div x-data="toaster" x-on:toast.window="add($event.detail)" aria-live="polite" class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition:enter="transform transition ease-out duration-200" x-transition:enter-start="translate-y-2 opacity-0" x-transition:enter-end="translate-y-0 opacity-100"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl bg-slate-900 px-4 py-3 text-sm text-white shadow-xl ring-1 ring-black/5 dark:bg-white dark:text-slate-900">
            <span class="mt-0.5" :class="{ 'text-emerald-400 dark:text-emerald-600': toast.type === 'success', 'text-rose-400 dark:text-rose-600': toast.type === 'error', 'text-amber-400 dark:text-amber-600': toast.type === 'warning' }">
                <template x-if="toast.type === 'error' || toast.type === 'warning'"><x-hicon name="exclamation-triangle" /></template>
                <template x-if="toast.type !== 'error' && toast.type !== 'warning'"><x-hicon name="check-circle" /></template>
            </span>
            <p class="flex-1 font-medium" x-text="toast.message"></p>
            <button type="button" x-show="toast.undo" x-on:click="undo(toast)" class="font-semibold text-sand-400 hover:text-sand-500 dark:text-amber-600">Undo</button>
            <button type="button" x-on:click="remove(toast.id)" class="text-slate-400 hover:text-white dark:hover:text-slate-900"><span class="sr-only">Dismiss</span><x-hicon name="x-mark" class="size-4" /></button>
        </div>
    </template>
</div>
@if (session('toast'))
    <script>document.addEventListener('alpine:initialized', () => window.dispatchEvent(new CustomEvent('toast', { detail: @js(session('toast')) })), { once: true });</script>
@endif
</body>
</html>
