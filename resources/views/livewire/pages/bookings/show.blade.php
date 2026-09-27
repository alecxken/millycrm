<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public Booking $booking;

    public ?string $panel = null;

    public array $payment = [];

    public ?string $feedbackLink = null;

    public function mount(Booking $booking): void
    {
        $this->authorize('view', $booking);
        $this->booking = $booking;
        $this->resetPayment();
    }

    public function rendering($view): void
    {
        $view->title('Booking '.$this->booking->reference);
    }

    public function resetPayment(): void
    {
        $this->payment = ['amount' => (string) $this->booking->balance(), 'method' => 'mpesa', 'reference' => '', 'paid_at' => now()->format('Y-m-d\TH:i')];
        $this->resetValidation();
    }

    public function recordPayment(BookingService $bookings): void
    {
        $this->authorize('update', $this->booking);
        $data = $this->validate([
            'payment.amount' => ['required', 'numeric', 'min:1', 'max:'.max(1, $this->booking->balance())],
            'payment.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payment.reference' => ['nullable', 'string', 'max:40'],
            'payment.paid_at' => ['required', 'date', 'before_or_equal:now'],
        ], ['payment.amount.max' => 'That is more than the outstanding balance.'], ['payment.amount' => 'amount'])['payment'];

        $bookings->recordPayment($this->booking, $data);
        $this->booking->refresh();
        $this->panel = null;
        $this->resetPayment();
        $this->dispatch('toast', message: 'Payment recorded. Status: '.$this->booking->payment_status->label().'.');
    }

    public function cancel(BookingService $bookings): void
    {
        $this->authorize('update', $this->booking);
        $bookings->cancel($this->booking);
        $this->booking->refresh();
        $this->dispatch('toast', message: 'Booking cancelled.', type: 'warning');
    }

    public function generateFeedbackLink(BookingService $bookings): void
    {
        $this->feedbackLink = $bookings->feedbackUrl($this->booking);
    }

    public function with(): array
    {
        $this->booking->load(['customer:id,first_name,last_name,company_name,type,phone,email,passport_expiry', 'consultant:id,name,avatar_color', 'payments' => fn ($q) => $q->latest('paid_at'), 'quote.items.supplier:id,name', 'feedback', 'tickets', 'campaign:id,name']);

        return ['paidPct' => (float) $this->booking->total_amount > 0 ? min(100, round($this->booking->amount_paid / $this->booking->total_amount * 100)) : 0];
    }
}; ?>

@php($b = $booking)
<div>
    <nav class="mb-4 text-sm"><a href="{{ route('bookings.index') }}" wire:navigate class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"><x-hicon name="arrow-left" class="size-4" /> Bookings</a></nav>

    <x-page-header :title="$b->destination" :subtitle="$b->reference.' · '.fdate($b->start_date).' – '.fdate($b->end_date).' · '.$b->nights().' nights'">
        <x-slot:actions>
            <x-badge :enum="$b->status" /><x-badge :enum="$b->payment_status" />
            @can('update', $b)
                @if ($b->balance() > 0 && $b->status !== BookingStatus::Cancelled)<x-button icon="banknotes" wire:click="$set('panel', 'payment')">Record payment</x-button>@endif
                @if ($b->status === BookingStatus::Confirmed)<x-button variant="ghost" icon="x-circle" wire:click="cancel" wire:confirm="Cancel booking {{ $b->reference }}? Payments already made will be marked for refund.">Cancel</x-button>@endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($b->customer->passportExpiresSoon() && $b->start_date->isFuture())
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200" role="alert">
            <x-hicon name="exclamation-triangle" /><p><strong>Passport risk:</strong> {{ $b->customer->display_name }}'s passport expires {{ fdate($b->customer->passport_expiry) }} — within 6 months of travel. Contact them before departure.</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Payments">
                <div class="mb-4">
                    <div class="flex justify-between text-sm"><span class="font-semibold">{{ money($b->amount_paid, $b->currency) }} paid</span><span class="text-slate-500">of {{ money($b->total_amount, $b->currency) }}</span></div>
                    <div class="mt-2 h-2.5 rounded-full bg-slate-100 dark:bg-slate-800" role="progressbar" aria-valuenow="{{ $paidPct }}" aria-valuemin="0" aria-valuemax="100"><div class="h-2.5 rounded-full bg-brand-600" style="width: {{ $paidPct }}%"></div></div>
                    @if ($b->balance() > 0)<p class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-400">Balance due: {{ money($b->balance(), $b->currency) }}</p>@endif
                </div>
                @forelse ($b->payments as $p)
                    <div class="flex items-center gap-3 border-t border-slate-100 py-3 text-sm dark:border-slate-800">
                        <x-badge :enum="$p->method" />
                        <span class="flex-1 font-mono text-xs text-slate-500">{{ $p->reference }}</span>
                        <span class="text-xs text-slate-500">{{ fdate($p->paid_at, true) }}</span>
                        <span class="font-semibold tabular-nums">{{ money($p->amount, $b->currency) }}</span>
                    </div>
                @empty
                    <p class="border-t border-slate-100 pt-3 text-sm text-slate-500 dark:border-slate-800">No payments yet — request the deposit to secure the booking.</p>
                @endforelse
            </x-card>

            @if ($b->quote)
                <x-card title="What's included" :subtitle="'From quote '.$b->quote->reference" :padding="false">
                    <x-slot:actions><a href="{{ route('quotes.print', $b->quote) }}" target="_blank" class="link text-xs">Print quote</a></x-slot:actions>
                    @foreach ($b->quote->items as $line)
                        <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-0 dark:border-slate-800">
                            <x-badge :enum="$line->type" size="xs" />
                            <div class="min-w-0 flex-1"><p class="truncate">{{ $line->description }}</p><p class="text-xs text-slate-500">{{ $line->supplier?->name }}</p></div>
                            <span class="tabular-nums">{{ money($line->price, $b->currency) }}</span>
                        </div>
                    @endforeach
                </x-card>
            @endif

            <x-card title="After the trip" subtitle="Feedback closes the loop and feeds NPS">
                @if ($b->feedback)
                    <div class="flex items-start gap-3">
                        <x-badge :color="$b->feedback->nps_score >= 9 ? 'emerald' : ($b->feedback->nps_score >= 7 ? 'slate' : 'rose')" icon="heart" :label="'NPS '.$b->feedback->nps_score" />
                        <div><p class="text-sm">{{ str_repeat('★', $b->feedback->rating) }}{{ str_repeat('☆', 5 - $b->feedback->rating) }}</p><p class="mt-1 text-sm">“{{ $b->feedback->comment }}”</p><p class="mt-1 text-xs text-slate-500">{{ fdate($b->feedback->submitted_at) }}</p></div>
                    </div>
                @else
                    <p class="text-sm text-slate-600 dark:text-slate-400">No feedback yet. Send the traveller their private feedback link (valid 30 days, no login needed).</p>
                    <div class="mt-3">
                        @if ($feedbackLink)
                            <div x-data="{ copied: false }" class="flex gap-2">
                                <input readonly value="{{ $feedbackLink }}" class="form-input font-mono text-xs" aria-label="Feedback link" x-ref="link">
                                <x-button variant="secondary" icon="clipboard" x-on:click="navigator.clipboard.writeText($refs.link.value); copied = true; setTimeout(() => copied = false, 2000)"><span x-text="copied ? 'Copied' : 'Copy'"></span></x-button>
                                <x-button variant="ghost" icon="arrow-top-right-on-square" :href="$feedbackLink" target="_blank">Open</x-button>
                            </div>
                        @else
                            <x-button variant="secondary" icon="link" wire:click="generateFeedbackLink">Create feedback link</x-button>
                        @endif
                    </div>
                @endif
            </x-card>
        </div>

        <aside class="space-y-6">
            <x-card title="Traveller">
                <a href="{{ route('customers.show', $b->customer) }}" wire:navigate class="font-semibold hover:text-brand-700">{{ $b->customer->display_name }}</a>
                <p class="text-sm text-slate-500">{{ $b->customer->phone }}</p>
                <p class="text-sm text-slate-500">{{ $b->customer->email }}</p>
                @if ($b->consultant)<div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-4 text-sm dark:border-slate-800"><x-avatar :user="$b->consultant" size="sm" /><span>Handled by <strong>{{ $b->consultant->name }}</strong></span></div>@endif
                @if ($b->campaign)<p class="mt-3 text-xs text-slate-500">Attributed to campaign <strong>{{ $b->campaign->name }}</strong></p>@endif
            </x-card>
            <x-card title="Service tickets" :padding="false">
                @forelse ($b->tickets as $t)
                    <div class="flex items-center gap-2 border-b border-slate-100 px-4 py-3 text-sm last:border-0 dark:border-slate-800">
                        <span class="min-w-0 flex-1 truncate">{{ $t->subject }}</span><x-badge :enum="$t->status" size="xs" />
                    </div>
                @empty
                    <p class="px-4 py-4 text-sm text-slate-500">No issues reported. 🎉</p>
                @endforelse
            </x-card>
        </aside>
    </div>

    <x-slide-over name="payment" title="Record a payment" :description="'Balance '.money($b->balance(), $b->currency)" width="max-w-md">
        <form wire:submit="recordPayment" id="pay-form" class="space-y-4">
            <x-field label="Amount ({{ $b->currency->value }})" for="pay-amt" error="payment.amount" required><input id="pay-amt" type="number" step="0.01" min="1" wire:model="payment.amount" class="form-input"></x-field>
            <x-field label="Method">
                <div class="grid grid-cols-2 gap-2">
                    @foreach (PaymentMethod::cases() as $m)
                        <label @class(['flex cursor-pointer items-center gap-2 rounded-xl border p-2.5 text-sm font-medium', 'border-brand-600 bg-brand-50 dark:bg-brand-400/10' => $payment['method'] === $m->value, 'border-slate-200 dark:border-slate-700' => $payment['method'] !== $m->value])>
                            <input type="radio" wire:model.live="payment.method" value="{{ $m->value }}" class="sr-only"><x-hicon :name="$m->icon()" class="size-4" />{{ $m->label() }}
                        </label>
                    @endforeach
                </div>
            </x-field>
            <x-field :label="$payment['method'] === 'mpesa' ? 'M-Pesa code' : 'Reference'" for="pay-ref"><input id="pay-ref" wire:model="payment.reference" class="form-input font-mono uppercase" placeholder="{{ $payment['method'] === 'mpesa' ? 'e.g. SJK4H7X2PQ' : '' }}"></x-field>
            <x-field label="Paid at" for="pay-at" error="payment.paid_at"><input id="pay-at" type="datetime-local" wire:model="payment.paid_at" class="form-input"></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="pay-form" loading="recordPayment">Record payment</x-button></x-slot:footer>
    </x-slide-over>
</div>
