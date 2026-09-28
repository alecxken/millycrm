@php($e = $quote->enquiry)
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quote {{ $quote->reference }} · WanderLink Travel</title>
    @vite(['resources/css/app.css'])
    <style>{!! app(\App\Services\ThemeService::class)->cssVariables() !!}</style>
    <style>@page { size: A4; margin: 14mm; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }</style>
</head>
<body class="bg-slate-100 font-sans text-slate-900 antialiased print:bg-white">
    <div class="no-print sticky top-0 z-10 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-6 py-3">
        <a href="{{ route('quotes.edit', $quote) }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">← Back to quote builder</a>
        <button onclick="window.print()" class="rounded-lg bg-brand-700 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-800">Print / Save as PDF</button>
    </div>

    <main class="mx-auto my-8 max-w-[210mm] bg-white p-10 shadow-sm print:my-0 print:p-0 print:shadow-none">
        <header class="flex items-start justify-between border-b-4 border-brand-700 pb-6">
            <div class="flex items-center gap-3">
                <x-application-logo class="size-12" />
                <div>
                    <p class="text-xl font-bold">WanderLink Travel</p>
                    <p class="text-xs text-slate-500">Kimathi Street, Nairobi · +254 700 123 456 · hello@wanderlink.test</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold tracking-tight text-brand-700">QUOTATION</p>
                <p class="text-sm font-semibold">{{ $quote->reference }}</p>
                <p class="text-xs text-slate-500">Issued {{ fdate($quote->sent_at ?? $quote->created_at) }} · valid until {{ fdate($quote->valid_until) }}</p>
            </div>
        </header>

        <section class="mt-6 grid grid-cols-2 gap-6 text-sm">
            <div>
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Prepared for</p>
                <p class="mt-1 font-semibold">{{ $e->customer->display_name }}</p>
                @if ($e->customer->company_name)<p>Attn: {{ $e->customer->full_name }}</p>@endif
                <p class="text-slate-600">{{ $e->customer->email }}</p>
                <p class="text-slate-600">{{ $e->customer->phone }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-wide text-slate-500 uppercase">Trip</p>
                <p class="mt-1 font-semibold">{{ $e->destination }}</p>
                <p class="text-slate-600">{{ fdate($e->departure_date) }} – {{ fdate($e->return_date) }}</p>
                <p class="text-slate-600">{{ $e->travellers_adults }} adults{{ $e->travellers_children ? ', '.$e->travellers_children.' children' : '' }}</p>
            </div>
        </section>

        <table class="mt-8 w-full text-sm">
            <thead><tr class="border-b-2 border-slate-200 text-left text-xs tracking-wide text-slate-500 uppercase"><th class="py-2">Description</th><th class="py-2">Type</th><th class="py-2 text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($quote->items as $line)
                    <tr class="border-b border-slate-100"><td class="py-3 pr-4">{{ $line->description }}</td><td class="py-3 text-slate-600">{{ $line->type->label() }}</td><td class="py-3 text-right tabular-nums">{{ money($line->price, $quote->currency) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot class="text-sm">
                @if ((float) $quote->discount > 0)
                    <tr><td></td><td class="pt-3 text-slate-600">Subtotal</td><td class="pt-3 text-right tabular-nums">{{ money($quote->subtotal(), $quote->currency) }}</td></tr>
                    <tr><td></td><td class="text-slate-600">Discount</td><td class="text-right tabular-nums">– {{ money($quote->discount, $quote->currency) }}</td></tr>
                @endif
                <tr><td></td><td class="pt-3 text-base font-bold">Total</td><td class="pt-3 text-right text-base font-bold tabular-nums">{{ money($quote->total_amount, $quote->currency) }}</td></tr>
            </tfoot>
        </table>

        @if ($quote->notes)
            <section class="mt-8 rounded-xl bg-slate-50 p-4 text-sm"><p class="mb-1 font-semibold">Notes</p><p class="whitespace-pre-line text-slate-700">{{ $quote->notes }}</p></section>
        @endif

        <section class="mt-8 grid grid-cols-2 gap-6 text-xs text-slate-600">
            <div>
                <p class="mb-1 font-semibold text-slate-900">Payment</p>
                <p>30% deposit to confirm, balance 30 days before departure.</p>
                <p>M-Pesa Paybill 123456 · Account {{ $quote->reference }}</p>
                <p>Bank: KCB Kimathi St · A/C 1234567890</p>
            </div>
            <div>
                <p class="mb-1 font-semibold text-slate-900">Your consultant</p>
                <p>{{ $e->consultant?->name ?? 'WanderLink Travel' }}</p>
                <p>Prices subject to availability until booked. Your data is handled under the Kenya Data Protection Act 2019.</p>
            </div>
        </section>
    </main>
</body>
</html>
