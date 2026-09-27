<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Bookings')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $when = 'upcoming';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $payment = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $user = auth()->user();
        $query = Booking::visibleTo($user)
            ->with(['customer:id,first_name,last_name,company_name,type', 'consultant:id,name,avatar_color'])
            ->when($this->search, fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$this->search}%")->orWhere('destination', 'like', "%{$this->search}%")->orWhereHas('customer', fn ($c) => $c->search($this->search))))
            ->when($this->payment, fn ($q) => $q->where('payment_status', $this->payment));

        match ($this->when) {
            'upcoming' => $query->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Travelling])->orderBy('start_date'),
            'past' => $query->where('status', BookingStatus::Completed)->orderByDesc('end_date'),
            'cancelled' => $query->where('status', BookingStatus::Cancelled)->latest(),
            default => $query->latest(),
        };

        $upcoming = Booking::visibleTo($user)->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Travelling]);

        return [
            'bookings' => $query->paginate(15),
            'outstanding' => (clone $upcoming)->get(['total_amount', 'amount_paid', 'currency'])->sum(fn ($b) => $b->balance() * $b->currency->toKes()),
            'upcomingCount' => (clone $upcoming)->count(),
            'departingWeek' => (clone $upcoming)->whereBetween('start_date', [now()->startOfDay(), now()->addDays(7)])->count(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Bookings" subtitle="Confirmed trips, payments and who's travelling next." />

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat label="Upcoming trips" :value="$upcomingCount" icon="ticket" />
        <x-stat label="Departing in 7 days" :value="$departingWeek" icon="paper-airplane" tone="sky" />
        <x-stat label="Balance to collect" :value="money($outstanding)" icon="banknotes" tone="amber" hint="On upcoming trips" />
    </div>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div class="inline-flex max-w-full overflow-x-auto rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800" role="tablist">
            @foreach (['upcoming' => 'Upcoming', 'past' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label)
                <button type="button" role="tab" wire:click="$set('when', '{{ $key }}')" aria-selected="{{ $when === $key ? 'true' : 'false' }}" @class(['shrink-0 rounded-lg px-3 py-1.5 font-medium', 'bg-white shadow-sm dark:bg-slate-950' => $when === $key, 'text-slate-600 dark:text-slate-400' => $when !== $key])>{{ $label }}</button>
            @endforeach
        </div>
        <div class="flex gap-3">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="BK- reference, customer, destination" class="form-input sm:w-72" aria-label="Search bookings">
            <select wire:model.live="payment" class="form-input w-40" aria-label="Payment status"><option value="">Any payment</option>@foreach (PaymentStatus::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </div>
    </div>

    <div class="card overflow-hidden" wire:loading.class="opacity-60">
        @if ($bookings->isEmpty())
            <x-empty-state icon="ticket" title="No bookings here" description="Bookings are created in one click from an accepted quote in the pipeline.">
                <x-button variant="secondary" :href="route('pipeline')" wire:navigate icon="view-columns">Open the pipeline →</x-button>
            </x-empty-state>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Booking</th><th>Customer</th><th>Dates</th><th>Status</th><th>Payment</th><th class="text-right">Total</th><th class="text-right">Balance</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($bookings as $b)
                            <tr wire:key="b-{{ $b->id }}" class="relative hover:bg-slate-50 dark:hover:bg-slate-800/40">
                                <td><a href="{{ route('bookings.show', $b) }}" wire:navigate class="font-semibold text-slate-900 after:absolute after:inset-0 dark:text-white">{{ $b->destination }}</a><p class="text-xs text-slate-500">{{ $b->reference }}</p></td>
                                <td>{{ $b->customer->display_name }}</td>
                                <td class="whitespace-nowrap text-xs">{{ fdate($b->start_date) }} – {{ fdate($b->end_date) }}@if ($b->start_date->isFuture() && $b->start_date->lte(now()->addDays(7)))<br><span class="font-semibold text-sky-700 dark:text-sky-400">Departs {{ $b->start_date->diffForHumans() }}</span>@endif</td>
                                <td><x-badge :enum="$b->status" /></td>
                                <td><x-badge :enum="$b->payment_status" /></td>
                                <td class="text-right tabular-nums">{{ money($b->total_amount, $b->currency) }}</td>
                                <td @class(['text-right tabular-nums', 'font-semibold text-amber-700 dark:text-amber-400' => $b->balance() > 0])>{{ $b->balance() > 0 ? money($b->balance(), $b->currency) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 px-4 py-3 dark:border-slate-800">{{ $bookings->links() }}</div>
        @endif
    </div>
</div>
