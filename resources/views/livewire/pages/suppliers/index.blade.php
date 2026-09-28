<?php

use App\Enums\SupplierCategory;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Suppliers')] class extends Component
{
    #[Url]
    public string $category = '';

    #[Url(as: 'q')]
    public string $search = '';

    public ?string $panel = null;

    public ?int $editingId = null;

    public array $form = [];

    public function mount(): void
    {
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = ['name' => '', 'category' => 'hotel', 'contact_name' => '', 'email' => '', 'phone' => '', 'commission_rate' => '10', 'rating' => '4.0'];
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $s = Supplier::findOrFail($id);
        $this->resetForm();
        $this->editingId = $s->id;
        $this->form = [...$s->only(['name', 'contact_name', 'email', 'phone']), 'category' => $s->category->value, 'commission_rate' => (string) (float) $s->commission_rate, 'rating' => (string) (float) $s->rating];
        $this->panel = 'supplier';
    }

    public function create(): void
    {
        $this->resetForm();
        $this->panel = 'supplier';
    }

    public function save(): void
    {
        $this->editingId ? $this->authorize('update', Supplier::findOrFail($this->editingId)) : $this->authorize('create', Supplier::class);
        $data = $this->validate([
            'form.name' => 'required|string|max:120',
            'form.category' => ['required', Rule::enum(SupplierCategory::class)],
            'form.contact_name' => 'nullable|string|max:120',
            'form.email' => 'nullable|email|max:120',
            'form.phone' => 'nullable|string|max:40',
            'form.commission_rate' => 'required|numeric|between:0,100',
            'form.rating' => 'required|numeric|between:1,5',
        ], attributes: ['form.name' => 'name', 'form.commission_rate' => 'commission', 'form.rating' => 'rating'])['form'];

        Supplier::updateOrCreate(['id' => $this->editingId], $data);
        $this->panel = null;
        $this->dispatch('toast', message: $this->editingId ? 'Supplier updated.' : 'Supplier added.');
        $this->resetForm();
    }

    public function with(): array
    {
        // Bookings volume and revenue per supplier, via accepted quote lines.
        $volume = DB::table('quote_items')
            ->join('bookings', 'bookings.quote_id', '=', 'quote_items.quote_id')
            ->where('bookings.status', '!=', 'cancelled')
            ->whereNotNull('quote_items.supplier_id')
            ->groupBy('quote_items.supplier_id')
            ->selectRaw('quote_items.supplier_id, count(distinct bookings.id) as bookings, sum(quote_items.cost) as spend, sum(quote_items.price - quote_items.cost) as margin')
            ->get()->keyBy('supplier_id');

        $suppliers = Supplier::query()
            ->when($this->category, fn ($q) => $q->where('category', $this->category))
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')->get()
            ->map(fn ($s) => ['model' => $s, 'bookings' => (int) ($volume[$s->id]->bookings ?? 0), 'spend' => (float) ($volume[$s->id]->spend ?? 0), 'margin' => (float) ($volume[$s->id]->margin ?? 0)])
            ->sortByDesc('bookings')->values();

        return ['suppliers' => $suppliers, 'maxBookings' => max(1, $suppliers->max('bookings'))];
    }
}; ?>

<div>
    <x-page-header title="Suppliers" subtitle="The airlines, lodges and operators we book with, and how much we send their way.">
        <x-slot:actions>@can('suppliers.manage')<x-button icon="plus" wire:click="create">Add supplier</x-button>@endcan</x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search suppliers" class="form-input sm:w-72" aria-label="Search suppliers">
        <div class="flex gap-2 overflow-x-auto">
            <button type="button" wire:click="$set('category', '')" @class(['shrink-0 rounded-full border px-3 py-1.5 text-sm font-medium', 'border-brand-600 bg-brand-50 text-brand-800 dark:bg-brand-400/10 dark:text-brand-200' => $category === '', 'border-slate-200 dark:border-slate-700' => $category !== ''])>All</button>
            @foreach (SupplierCategory::cases() as $cat)
                <button type="button" wire:click="$set('category', '{{ $cat->value }}')" @class(['inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium', 'border-brand-600 bg-brand-50 text-brand-800 dark:bg-brand-400/10 dark:text-brand-200' => $category === $cat->value, 'border-slate-200 dark:border-slate-700' => $category !== $cat->value])><x-hicon :name="$cat->icon()" class="size-4" />{{ $cat->label() }}</button>
            @endforeach
        </div>
    </div>

    <div class="card overflow-hidden">
        @if ($suppliers->isEmpty())
            <x-empty-state icon="building-office-2" title="No suppliers found" description="Add the airlines, hotels and operators you book with to build quotes faster." />
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Supplier</th><th>Category</th><th class="text-right">Commission</th><th>Rating</th><th class="w-56">Bookings volume</th><th class="text-right">Spend</th><th class="text-right">Our margin</th><th></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($suppliers as $row)
                            @php($s = $row['model'])
                            <tr wire:key="s-{{ $s->id }}">
                                <td><p class="font-semibold text-slate-900 dark:text-white">{{ $s->name }}</p><p class="text-xs text-slate-500">{{ $s->contact_name }} · {{ $s->phone }}</p></td>
                                <td><x-badge :enum="$s->category" /></td>
                                <td class="text-right tabular-nums">{{ rtrim(rtrim($s->commission_rate, '0'), '.') }}%</td>
                                <td class="whitespace-nowrap"><span class="text-sand-500" aria-hidden="true">{{ str_repeat('★', (int) round($s->rating)) }}</span><span class="text-slate-300 dark:text-slate-700" aria-hidden="true">{{ str_repeat('★', 5 - (int) round($s->rating)) }}</span> <span class="text-xs text-slate-500">{{ $s->rating }}</span></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-brand-600" style="width: {{ round($row['bookings'] / $maxBookings * 100) }}%"></div></div>
                                        <span class="w-8 text-right text-xs font-semibold tabular-nums">{{ $row['bookings'] }}</span>
                                    </div>
                                </td>
                                <td class="text-right tabular-nums">{{ $row['spend'] ? money($row['spend'], compact: true) : '—' }}</td>
                                <td class="text-right tabular-nums">{{ $row['margin'] ? money($row['margin'], compact: true) : '—' }}</td>
                                <td>@can('update', $s)<button type="button" wire:click="edit({{ $s->id }})" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"><span class="sr-only">Edit {{ $s->name }}</span><x-hicon name="pencil-square" class="size-4" /></button>@endcan</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <x-slide-over name="supplier" :title="$editingId ? 'Edit supplier' : 'Add supplier'" width="max-w-md">
        <form wire:submit="save" id="sup-form" class="space-y-4">
            <x-field label="Name" for="sp-name" error="form.name" required><input id="sp-name" wire:model="form.name" class="form-input"></x-field>
            <x-field label="Category" for="sp-cat"><select id="sp-cat" wire:model="form.category" class="form-input">@foreach (SupplierCategory::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
            <x-field label="Contact person" for="sp-contact"><input id="sp-contact" wire:model="form.contact_name" class="form-input"></x-field>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Email" for="sp-email" error="form.email"><input id="sp-email" type="email" wire:model="form.email" class="form-input"></x-field>
                <x-field label="Phone" for="sp-phone"><input id="sp-phone" wire:model="form.phone" class="form-input"></x-field>
                <x-field label="Commission %" for="sp-comm" error="form.commission_rate"><input id="sp-comm" type="number" step="0.5" min="0" max="100" wire:model="form.commission_rate" class="form-input"></x-field>
                <x-field label="Rating (1–5)" for="sp-rate" error="form.rating"><input id="sp-rate" type="number" step="0.1" min="1" max="5" wire:model="form.rating" class="form-input"></x-field>
            </div>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="sup-form" loading="save">Save</x-button></x-slot:footer>
    </x-slide-over>
</div>
