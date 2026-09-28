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
             class="fixed inset-y-2 left-2 flex w-72 max-w-[85vw] flex-col rounded-3xl bg-white shadow-xl dark:bg-slate-900">
            <div class="flex items-center justify-between px-4 py-4">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                    <x-application-logo class="size-9" />
                    <span class="text-base font-bold">WanderLink <span class="font-medium text-slate-500">CRM</span></span>
                </a>
                <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" x-on:click="mobileOpen = false">
                    <span class="sr-only">Close menu</span><x-hicon name="x-mark" />
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-3.5 pb-4" x-data="{ collapsed: false }">
                @include('layouts.partials.nav')
            </div>
            <div class="flex items-center gap-3 border-t border-slate-100 p-4 dark:border-slate-800">
                <x-avatar :user="auth()->user()" size="sm" />
                <div class="min-w-0 flex-1"><p class="truncate text-[12.5px] font-bold">{{ auth()->user()->name }}</p><a href="{{ route('profile') }}" wire:navigate class="text-[11px] text-slate-500">Profile</a></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="rounded-lg p-2 text-slate-400 hover:text-slate-700" title="Sign out"><span class="sr-only">Sign out</span><x-hicon name="arrow-right-start-on-rectangle" class="size-5" /></button></form>
            </div>
        </div>
    </div>

    {{-- Desktop sidebar: a white rail floating on the wash --}}
    <aside class="rail hidden lg:fixed lg:top-3 lg:bottom-3 lg:left-3 lg:z-40 lg:flex lg:flex-col lg:rounded-[calc(24px*var(--radius-scale))] lg:bg-white lg:transition-[width] lg:duration-200 dark:lg:bg-slate-900"
           :class="collapsed ? 'lg:w-[78px]' : 'lg:w-[252px]'">
        <div class="flex shrink-0 items-center gap-3 px-5 pt-6 pb-4" :class="collapsed && 'justify-center !px-2'">
            <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3">
                <x-application-logo class="size-9 shrink-0" />
                <span x-show="!collapsed" class="flex items-center gap-3">
                    <span class="h-7 w-px bg-slate-200 dark:bg-slate-700" aria-hidden="true"></span>
                    <span class="leading-tight"><span class="block text-[14px] font-bold text-brand-900 dark:text-white">WanderLink</span><span class="block text-[10.5px] font-normal text-slate-500">Travel CRM</span></span>
                </span>
            </a>
        </div>
        <nav class="flex-1 overflow-y-auto px-3.5 pb-3" aria-label="Main">
            @include('layouts.partials.nav')
        </nav>
        <div class="mx-3.5 h-px bg-slate-100 dark:bg-slate-800"></div>
        <div class="p-3">
            <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()" class="flex w-full items-center gap-3 rounded-[14px] p-2 text-left transition hover:bg-slate-100/70 dark:hover:bg-slate-800" :class="collapsed && 'justify-center'">
                    <x-avatar :user="auth()->user()" size="sm" />
                    <span x-show="!collapsed" class="min-w-0 flex-1">
                        <span class="block truncate text-[12.5px] font-bold text-slate-700 dark:text-white">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-[10.5px] font-normal text-slate-400">{{ auth()->user()->primaryRole()?->label() }}</span>
                    </span>
                    <x-hicon x-show="!collapsed" name="chevron-up-down" class="size-4 text-slate-300" />
                </button>
                <div x-cloak x-show="open" x-transition.origin.bottom.left class="absolute bottom-full left-0 z-50 mb-2 w-56 overflow-hidden rounded-2xl border border-[var(--hairline)] bg-white py-1 shadow-[var(--shadow-lift)] dark:bg-slate-800">
                    <a href="{{ route('profile') }}" wire:navigate class="block px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-[var(--hover-wash)] dark:text-slate-300">Profile & password</a>
                    @can('settings.manage')<a href="{{ route('admin.appearance') }}" wire:navigate class="block px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-[var(--hover-wash)] dark:text-slate-300">Appearance</a>@endcan
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-start text-sm font-semibold text-slate-600 hover:bg-[var(--hover-wash)] dark:text-slate-300">Sign out</button>
                    </form>
                </div>
            </div>
            <button type="button" x-on:click="collapsed = !collapsed" class="mt-1 flex w-full items-center gap-3 rounded-[14px] px-3.5 py-2 text-[12px] font-semibold text-slate-400 transition hover:bg-slate-100/70 hover:text-slate-600 dark:hover:bg-slate-800" :class="collapsed && 'justify-center !px-2'" :aria-expanded="(!collapsed).toString()">
                <x-hicon name="chevron-double-left" class="size-4 transition" x-bind:class="collapsed && 'rotate-180'" />
                <span x-show="!collapsed">Collapse</span><span x-show="collapsed" class="sr-only">Expand sidebar</span>
            </button>
        </div>
    </aside>

    <div class="transition-[padding] duration-200" :class="collapsed ? 'lg:pl-[90px]' : 'lg:pl-[264px]'">
        {{-- Top bar: transparent, sits straight on the wash --}}
        <header class="mx-auto flex w-full max-w-[1400px] items-center gap-3 px-4 pt-5 sm:px-8 lg:px-10">
            <button type="button" class="relative -ml-1 flex size-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 lg:hidden dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300" x-on:click="mobileOpen = true">
                <span class="sr-only">Open menu</span><x-hicon name="bars-3" class="size-5" />
            </button>

            <button type="button" x-data x-on:click="$dispatch('open-command-palette')"
                    class="flex h-11 min-w-0 flex-1 items-center gap-3 rounded-full border border-[var(--hairline)] bg-white/80 px-4 text-left text-[13px] text-slate-400 shadow-[var(--shadow-panel)] backdrop-blur transition hover:border-brand-300 sm:max-w-md dark:bg-slate-900/80">
                <x-hicon name="magnifying-glass" class="size-5 shrink-0" />
                <span class="flex-1 truncate">Find a customer, booking or phone number…</span>
                <kbd class="hidden rounded-md bg-slate-100 px-1.5 py-0.5 font-sans text-[10.5px] font-bold text-slate-500 sm:inline dark:bg-slate-800">⌘K</kbd>
            </button>

            <div class="ml-auto flex items-center gap-2">
                @can('pipeline.manage')
                    <div class="hidden sm:block"><x-button :href="route('pipeline', ['new' => 1])" wire:navigate icon="plus" pill>New enquiry</x-button></div>
                @endcan

                <button type="button" x-data x-on:click="$store.theme.cycle()" class="relative flex size-11 items-center justify-center rounded-full border border-[var(--hairline)] bg-white text-slate-500 transition hover:border-brand-400 hover:text-brand-700 active:scale-95 dark:bg-slate-900 dark:text-slate-400"
                        x-bind:title="'Theme: ' + $store.theme.value">
                    <span class="sr-only" x-text="'Theme: ' + $store.theme.value + '. Click to change.'"></span>
                    <span x-show="$store.theme.value === 'light'"><x-hicon name="sun" /></span>
                    <span x-show="$store.theme.value === 'dark'" x-cloak><x-hicon name="moon" /></span>
                    <span x-show="$store.theme.value === 'system'" x-cloak><x-hicon name="computer-desktop" /></span>
                </button>

                {{-- Accent rule: the brand's one punctuation mark --}}
                <span class="ml-1 hidden h-9 w-[3px] rounded-full bg-sand-500 xl:block" aria-hidden="true"></span>
            </div>
        </header>

        <main id="main" class="px-4 pt-6 pb-10 sm:px-8 lg:px-10">
            <div class="mx-auto max-w-[1400px]">
                {{ $slot }}
            </div>
        </main>
    </div>
</div>

<livewire:command-palette />

{{-- Toasts --}}
<div x-data="toaster" x-on:toast.window="add($event.detail)" aria-live="polite" class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition:enter="transform transition duration-[350ms] ease-[cubic-bezier(.34,1.56,.64,1)]" x-transition:enter-start="translate-y-[140%]" x-transition:enter-end="translate-y-0"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-[14px] bg-slate-700 px-5 py-3.5 text-[13.5px] font-semibold text-white shadow-[0_12px_32px_rgba(0,0,0,.25)] dark:bg-slate-100 dark:text-slate-900">
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
