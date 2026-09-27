<?php

use App\Enums\Currency;
use App\Enums\QuoteItemType;
use App\Enums\QuoteStatus;
use App\Enums\SupplierCategory;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Supplier;
use App\Services\QuoteService;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public Quote $quote;

    public array $item = [];

    public string $discount = '0';

    public string $currency = 'KES';

    public string $validUntil = '';

    public string $notes = '';

    /** Default markup (%) per line type — the agency's pricing policy. */
    public const MARKUPS = ['flight' => 8, 'hotel' => 15, 'tour' => 18, 'transfer' => 20, 'insurance' => 25, 'visa' => 30];

    public const SUPPLIER_FOR = ['flight' => 'airline', 'hotel' => 'hotel', 'tour' => 'tour_operator', 'transfer' => 'transfer', 'insurance' => 'insurer', 'visa' => 'visa_agent'];

    public function mount(Quote $quote): void
    {
        $this->quote = $quote->load('enquiry');
        $this->authorize('view', $quote);
        $this->discount = (string) (float) $quote->discount;
        $this->currency = $quote->currency->value;
        $this->validUntil = $quote->valid_until?->format('Y-m-d') ?? now()->addDays(14)->format('Y-m-d');
        $this->notes = (string) $quote->notes;
        $this->resetItem();
    }

    public function rendering($view): void
    {
        $view->title('Quote '.$this->quote->reference);
    }

    public function resetItem(string $type = 'flight'): void
    {
        $this->item = ['type' => $type, 'supplier_id' => '', 'description' => '', 'cost' => '', 'markup' => (string) self::MARKUPS[$type]];
        $this->resetValidation();
    }

    public function updatedItemType(string $type): void
    {
        $this->item['markup'] = (string) self::MARKUPS[$type];
        $this->item['supplier_id'] = '';
    }

    public function editable(): bool
    {
        return in_array($this->quote->status, [QuoteStatus::Draft, QuoteStatus::Sent], true) && ! $this->quote->booking()->exists();
    }

    public function addItem(QuoteService $quotes): void
    {
        $this->authorize('update', $this->quote);
        abort_unless($this->editable(), 403);

        $data = $this->validate([
            'item.type' => ['required', Rule::enum(QuoteItemType::class)],
            'item.supplier_id' => ['nullable', 'exists:suppliers,id'],
            'item.description' => ['required', 'string', 'max:255'],
            'item.cost' => ['required', 'numeric', 'min:0'],
            'item.markup' => ['required', 'numeric', 'min:0', 'max:100'],
        ], attributes: ['item.description' => 'description', 'item.cost' => 'cost', 'item.markup' => 'markup'])['item'];

        $quotes->addItem($this->quote, [...$data, 'supplier_id' => $data['supplier_id'] ?: null]);
        $this->resetItem($data['type'] === 'flight' ? 'hotel' : $data['type']);
        $this->quote->refresh();
        $this->dispatch('toast', message: 'Line added.');
    }

    public function removeItem(int $id, QuoteService $quotes): void
    {
        $this->authorize('update', $this->quote);
        abort_unless($this->editable(), 403);
        $quotes->removeItem($this->quote, QuoteItem::findOrFail($id));
        $this->quote->refresh();
        $this->dispatch('toast', message: 'Line removed.');
    }

    public function saveTerms(QuoteService $quotes): void
    {
        $this->authorize('update', $this->quote);
        abort_unless($this->editable(), 403);

        $this->validate([
            'discount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', Rule::enum(Currency::class)],
            'validUntil' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subtotal = (float) $this->quote->items()->sum('price');
        // Discounts over 5% need manager approval (Murray: security/accountability).
        if ((float) $this->discount > $subtotal * 0.05 && ! auth()->user()->can('approveDiscount', Quote::class)) {
            $this->addError('discount', 'Discounts above 5% ('.money($subtotal * 0.05, $this->currency).') need a manager to approve.');

            return;
        }

        $this->quote->update(['discount' => $this->discount, 'currency' => $this->currency, 'valid_until' => $this->validUntil, 'notes' => $this->notes ?: null]);
        $quotes->recalculate($this->quote);
        $this->dispatch('toast', message: 'Quote terms saved.');
    }

    public function send(QuoteService $quotes): void
    {
        $this->authorize('update', $this->quote);
        try {
            $quotes->send($this->quote);
        } catch (RuntimeException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }
        $this->quote->refresh();
        $this->dispatch('toast', message: 'Quote sent. A follow-up task was added for '.fdate(now()->addDays(QuoteService::FOLLOW_UP_AFTER_DAYS)).'.');
    }

    public function convert(QuoteService $quotes): void
    {
        $this->authorize('update', $this->quote);
        try {
            $booking = $quotes->convertToBooking($this->quote);
        } catch (RuntimeException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }
        session()->flash('toast', ['message' => "Booking {$booking->reference} confirmed. Enquiry marked as won.", 'type' => 'success']);
        $this->redirectRoute('bookings.show', $booking, navigate: true);
    }

    public function reject(): void
    {
        $this->authorize('update', $this->quote);
        $this->quote->update(['status' => QuoteStatus::Rejected]);
        $this->dispatch('toast', message: 'Quote marked as rejected. Build a revised quote from the enquiry if needed.');
    }

    public function with(): array
    {
        $this->quote->load(['items.supplier:id,name,category', 'enquiry.customer:id,first_name,last_name,company_name,type,email,phone', 'enquiry.consultant:id,name,avatar_color', 'booking:id,quote_id,reference']);
        $category = SupplierCategory::from(self::SUPPLIER_FOR[$this->item['type']] ?? 'airline');
        $cost = (float) ($this->item['cost'] ?: 0);
        $markup = (float) ($this->item['markup'] ?: 0);

        return [
            'suppliers' => Supplier::where('category', $category)->orderBy('name')->get(['id', 'name', 'commission_rate']),
            'preview' => QuoteService::priceFor($cost, $markup),
            'subtotal' => $this->quote->subtotal(),
            'cost' => $this->quote->totalCost(),
            'canEdit' => $this->editable() && auth()->user()->can('update', $this->quote),
        ];
    }
}; ?>

@php($e = $quote->enquiry)
<div>
    <nav class="mb-4 text-sm" aria-label="Breadcrumb">
        <a href="{{ route('pipeline', ['enquiry' => $e->id]) }}" wire:navigate class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"><x-hicon name="arrow-left" class="size-4" /> Pipeline · {{ $e->reference }}</a>
    </nav>

    <x-page-header :title="'Quote '.$quote->reference" :subtitle="$e->customer->display_name.' · '.$e->destination.' · '.fdate($e->departure_date).' – '.fdate($e->return_date).' · '.$e->travellers().' travellers'">
        <x-slot:actions>
            <x-badge :enum="$quote->status" />
            <x-button variant="secondary" icon="printer" :href="route('quotes.print', $quote)" target="_blank">Print / PDF</x-button>
            @if ($canEdit && $quote->status === \App\Enums\QuoteStatus::Draft)
                <x-button icon="paper-airplane" wire:click="send" :disabled="$quote->items->isEmpty()">Send to customer</x-button>
            @endif
            @if ($quote->booking)
                <x-button variant="accent" icon="ticket" :href="route('bookings.show', $quote->booking)" wire:navigate>View booking {{ $quote->booking->reference }}</x-button>
            @elseif ($canEdit && $quote->items->isNotEmpty())
                <x-button variant="accent" icon="check-badge" wire:click="convert" wire:confirm="Convert {{ $quote->reference }} into a confirmed booking for {{ money($quote->total_amount, $quote->currency) }}?">Convert to booking</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-card title="Line items" subtitle="Supplier cost + markup = customer price" :padding="false">
                @if ($quote->items->isEmpty())
                    <x-empty-state icon="document-text" title="No lines yet" description="Start with flights, then add the hotel, tours and extras. Markup is applied automatically." />
                @else
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Item</th><th class="text-right">Cost</th><th class="text-right">Markup</th><th class="text-right">Price</th><th class="w-10"><span class="sr-only">Remove</span></th></tr></thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($quote->items as $line)
                                    <tr wire:key="line-{{ $line->id }}">
                                        <td>
                                            <div class="flex items-start gap-2">
                                                <x-badge :enum="$line->type" size="xs" />
                                                <div><p class="font-medium text-slate-900 dark:text-white">{{ $line->description }}</p><p class="text-xs text-slate-500">{{ $line->supplier?->name ?? 'No supplier' }}</p></div>
                                            </div>
                                        </td>
                                        <td class="text-right tabular-nums text-slate-500">{{ money($line->cost, $quote->currency) }}</td>
                                        <td class="text-right tabular-nums text-slate-500">{{ rtrim(rtrim($line->markup, '0'), '.') }}%</td>
                                        <td class="text-right font-semibold tabular-nums">{{ money($line->price, $quote->currency) }}</td>
                                        <td>@if ($canEdit)<button type="button" wire:click="removeItem({{ $line->id }})" wire:confirm="Remove this line?" class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-400/10"><span class="sr-only">Remove {{ $line->description }}</span><x-hicon name="trash" class="size-4" /></button>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($canEdit)
                    <form wire:submit="addItem" class="border-t border-slate-100 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-900/40">
                        <p class="mb-3 text-sm font-semibold">Add a line</p>
                        <div class="mb-3 flex flex-wrap gap-2" role="radiogroup" aria-label="Line type">
                            @foreach (QuoteItemType::cases() as $t)
                                <label @class(['inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium', 'border-brand-600 bg-brand-50 text-brand-800 dark:bg-brand-400/10 dark:text-brand-200' => $item['type'] === $t->value, 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900' => $item['type'] !== $t->value])>
                                    <input type="radio" wire:model.live="item.type" value="{{ $t->value }}" class="sr-only"><x-hicon :name="$t->icon()" class="size-4" />{{ $t->label() }}
                                </label>
                            @endforeach
                        </div>
                        <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
                            <x-field label="Supplier" for="it-sup" class="col-span-2">
                                <select id="it-sup" wire:model="item.supplier_id" class="form-input"><option value="">— Choose —</option>@foreach ($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }} ({{ rtrim(rtrim($s->commission_rate, '0'), '.') }}% comm.)</option>@endforeach</select>
                            </x-field>
                            <x-field label="Description" for="it-desc" error="item.description" class="col-span-2 md:col-span-4" required>
                                <input id="it-desc" wire:model="item.description" class="form-input" placeholder="e.g. Return flights NBO–ZNZ, 2 adults">
                            </x-field>
                            <x-field label="Cost ({{ $quote->currency->value }})" for="it-cost" error="item.cost" class="md:col-span-2" required>
                                <input id="it-cost" type="number" min="0" step="0.01" wire:model.live.debounce.250ms="item.cost" class="form-input">
                            </x-field>
                            <x-field label="Markup %" for="it-mk" error="item.markup" class="md:col-span-1">
                                <input id="it-mk" type="number" min="0" max="100" step="0.5" wire:model.live.debounce.250ms="item.markup" class="form-input">
                            </x-field>
                            <div class="col-span-2 flex items-end justify-between gap-3 md:col-span-3">
                                <div class="text-sm"><p class="text-xs text-slate-500">Customer price</p><p class="text-lg font-bold tabular-nums">{{ money($preview, $quote->currency) }}</p></div>
                                <x-button type="submit" icon="plus" loading="addItem">Add line</x-button>
                            </div>
                        </div>
                    </form>
                @endif
            </x-card>
        </div>

        <aside class="space-y-6">
            <x-card title="Summary">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="tabular-nums">{{ money($subtotal, $quote->currency) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Discount</dt><dd class="tabular-nums">– {{ money($quote->discount, $quote->currency) }}</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-bold dark:border-slate-700"><dt>Total</dt><dd class="tabular-nums">{{ money($quote->total_amount, $quote->currency) }}</dd></div>
                    @if ($quote->currency->value !== 'KES')<div class="flex justify-between text-xs text-slate-500"><dt>≈ in KES</dt><dd>{{ money($quote->total_amount * $quote->currency->toKes()) }}</dd></div>@endif
                </dl>
                <div class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm dark:bg-emerald-400/10">
                    <p class="flex justify-between"><span class="text-emerald-800 dark:text-emerald-300">Agency margin</span><span class="font-semibold tabular-nums text-emerald-900 dark:text-emerald-200">{{ money($quote->margin(), $quote->currency) }}</span></p>
                    <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">{{ $quote->total_amount > 0 ? round($quote->margin() / $quote->total_amount * 100, 1) : 0 }}% of total · supplier cost {{ money($cost, $quote->currency) }}</p>
                </div>
            </x-card>

            <x-card title="Terms">
                <form wire:submit="saveTerms" class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <x-field label="Currency" for="q-cur"><select id="q-cur" wire:model="currency" class="form-input" @disabled(! $canEdit)>@foreach (Currency::cases() as $c)<option value="{{ $c->value }}">{{ $c->value }}</option>@endforeach</select></x-field>
                        <x-field label="Valid until" for="q-valid" error="validUntil"><input id="q-valid" type="date" wire:model="validUntil" class="form-input" @disabled(! $canEdit)></x-field>
                    </div>
                    <x-field label="Discount ({{ $quote->currency->value }})" for="q-disc" error="discount" hint="Over 5% requires manager approval."><input id="q-disc" type="number" min="0" step="100" wire:model="discount" class="form-input" @disabled(! $canEdit)></x-field>
                    <x-field label="Notes for the customer" for="q-notes"><textarea id="q-notes" wire:model="notes" rows="3" class="form-input" @disabled(! $canEdit) placeholder="Inclusions, exclusions, payment terms…"></textarea></x-field>
                    @if ($canEdit)<x-button type="submit" variant="secondary" class="w-full" loading="saveTerms">Save terms</x-button>@endif
                </form>
            </x-card>

            @if ($canEdit && $quote->status === \App\Enums\QuoteStatus::Sent)
                <button type="button" wire:click="reject" wire:confirm="Mark this quote as rejected by the customer?" class="w-full text-center text-sm font-medium text-rose-700 hover:underline dark:text-rose-400">Customer declined this quote</button>
            @endif
        </aside>
    </div>
</div>
