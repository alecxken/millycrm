<?php

use App\Models\Booking;
use App\Models\Customer;
use App\Models\Enquiry;
use Livewire\Volt\Component;

/**
 * Global Cmd/Ctrl+K search across customers, bookings and enquiries
 * by name, reference, phone or email. Respects row-level visibility.
 */
new class extends Component
{
    public string $query = '';

    public function with(): array
    {
        $term = trim($this->query);
        $user = auth()->user();

        if (mb_strlen($term) < 2) {
            return ['results' => []];
        }

        $like = '%'.$term.'%';
        $results = [];

        if ($user->can('customers.view')) {
            foreach (Customer::visibleTo($user)->search($term)->limit(6)->get() as $c) {
                $results[] = ['group' => 'Customers', 'title' => $c->display_name, 'meta' => collect([$c->phone, $c->email])->filter()->implode(' · '), 'url' => route('customers.show', $c), 'icon' => 'user', 'badge' => $c->lifecycle_stage];
            }
        }

        if ($user->can('pipeline.view')) {
            $bookings = Booking::visibleTo($user)->with('customer:id,first_name,last_name,company_name,type')
                ->where(fn ($q) => $q->where('reference', 'like', $like)->orWhere('destination', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->search($term)))
                ->latest('start_date')->limit(5)->get();
            foreach ($bookings as $b) {
                $results[] = ['group' => 'Bookings', 'title' => "{$b->reference} · {$b->destination}", 'meta' => $b->customer->display_name.' · '.fdate($b->start_date), 'url' => route('bookings.show', $b), 'icon' => 'ticket', 'badge' => $b->status];
            }

            $enquiries = Enquiry::visibleTo($user)->with('customer:id,first_name,last_name,company_name,type')
                ->where(fn ($q) => $q->where('reference', 'like', $like)->orWhere('destination', 'like', $like)
                    ->orWhereHas('customer', fn ($c) => $c->search($term)))
                ->latest()->limit(5)->get();
            foreach ($enquiries as $e) {
                $results[] = ['group' => 'Enquiries', 'title' => "{$e->reference} · {$e->destination}", 'meta' => $e->customer->display_name.' · '.money($e->expected_value), 'url' => route('pipeline', ['enquiry' => $e->id]), 'icon' => 'inbox', 'badge' => $e->status];
            }
        }

        return ['results' => $results];
    }
}; ?>

<div x-data="{
        open: false,
        active: 0,
        pages: @js(collect(app(\App\Support\Navigation::class)->for(auth()->user()))->flatten(1)->map(fn ($i) => ['title' => $i['label'], 'url' => route($i['route']), 'icon' => $i['icon']])->values()),
        get items() { return [...this.$refs.list?.querySelectorAll('[data-item]') ?? []] },
        show() { this.open = true; this.active = 0; this.$nextTick(() => this.$refs.input.focus()) },
        move(step) { const n = this.items.length; if (!n) return; this.active = (this.active + step + n) % n; this.items[this.active]?.scrollIntoView({ block: 'nearest' }) },
        go() { this.items[this.active]?.click() },
     }"
     x-on:keydown.window.prevent.cmd.k="show()" x-on:keydown.window.prevent.ctrl.k="show()"
     x-on:open-command-palette.window="show()"
     x-on:keydown.escape.window="open = false">
    <div x-cloak x-show="open" class="relative z-[70]" role="dialog" aria-modal="true" aria-label="Search">
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" x-on:click="open = false"></div>
        <div class="fixed inset-0 overflow-y-auto p-4 pt-[10vh] sm:p-6 sm:pt-[15vh]" x-on:click.self="open = false">
            <div x-show="open" x-transition x-trap.noscroll="open"
                 class="mx-auto max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/5 dark:bg-slate-900 dark:ring-white/10">
                <div class="flex items-center gap-3 border-b border-slate-200 px-4 dark:border-slate-800">
                    <x-hicon name="magnifying-glass" class="size-5 text-slate-400" />
                    <input x-ref="input" wire:model.live.debounce.200ms="query" type="text" placeholder="Name, phone, email, BK- or ENQ- reference…"
                           class="h-14 w-full border-0 bg-transparent text-slate-900 placeholder:text-slate-400 focus:ring-0 dark:text-white"
                           x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter.prevent="go()" x-on:input="active = 0">
                    <svg wire:loading wire:target="query" class="size-5 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                </div>

                <ul x-ref="list" class="max-h-[60vh] overflow-y-auto p-2" role="listbox">
                    @if (mb_strlen(trim($query)) < 2)
                        <li class="px-3 pb-1 pt-2 text-xs font-semibold tracking-wide text-slate-400 uppercase">Jump to</li>
                        <template x-for="(page, i) in pages" :key="page.url">
                            <li>
                                <a data-item :href="page.url" wire:navigate x-on:click="open = false" x-on:mouseenter="active = i"
                                   :class="active === i ? 'bg-brand-50 text-brand-900 dark:bg-brand-400/10 dark:text-brand-200' : 'text-slate-700 dark:text-slate-300'"
                                   class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium">
                                    <x-hicon name="arrow-right" class="size-4 text-slate-400" />
                                    <span x-text="page.title"></span>
                                </a>
                            </li>
                        </template>
                    @elseif (empty($results))
                        <li class="px-4 py-10 text-center text-sm text-slate-500">
                            No matches for “{{ $query }}”.
                            @can('customers.manage')<br><a href="{{ route('customers.index', ['new' => 1, 'name' => $query]) }}" wire:navigate class="link mt-2 inline-block">Add “{{ $query }}” as a new customer →</a>@endcan
                        </li>
                    @else
                        @foreach (collect($results)->groupBy('group') as $group => $items)
                            <li class="px-3 pb-1 pt-3 text-xs font-semibold tracking-wide text-slate-400 uppercase">{{ $group }}</li>
                            @foreach ($items as $item)
                                <li wire:key="cp-{{ md5($item['url']) }}">
                                    <a data-item href="{{ $item['url'] }}" wire:navigate x-on:click="open = false"
                                       x-on:mouseenter="active = items.indexOf($el)"
                                       :class="items[active] === $el ? 'bg-brand-50 dark:bg-brand-400/10' : ''"
                                       class="flex items-center gap-3 rounded-lg px-3 py-2">
                                        <span class="rounded-lg bg-slate-100 p-1.5 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><x-hicon :name="$item['icon']" class="size-4" /></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-slate-900 dark:text-white">{{ $item['title'] }}</span>
                                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $item['meta'] }}</span>
                                        </span>
                                        <x-badge :enum="$item['badge']" size="xs" />
                                    </a>
                                </li>
                            @endforeach
                        @endforeach
                    @endif
                </ul>
                <div class="flex items-center gap-4 border-t border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                    <span><kbd class="font-sans font-semibold">↑↓</kbd> navigate</span>
                    <span><kbd class="font-sans font-semibold">↵</kbd> open</span>
                    <span><kbd class="font-sans font-semibold">esc</kbd> close</span>
                </div>
            </div>
        </div>
    </div>
</div>
