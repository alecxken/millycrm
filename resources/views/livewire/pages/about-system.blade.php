<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Interaction;
use App\Support\DataCatalog;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('About the system')] class extends Component
{
    public function with(DataCatalog $catalog): array
    {
        $repeat = Customer::has('bookings', '>=', 2)->count();
        $booked = Customer::has('bookings')->count();

        return [
            'catalog' => $catalog->entries(),
            'stats' => [
                'customers' => Customer::count(),
                'interactions' => Interaction::count(),
                'enquiries' => Enquiry::count(),
                'repeatShare' => $booked ? round($repeat / $booked * 100) : 0,
                'repeatRevenueShare' => (function () {
                    $total = (float) Booking::where('status', '!=', BookingStatus::Cancelled)->sum('total_amount');
                    $repeatIds = Customer::has('bookings', '>=', 2)->pluck('id');
                    $repeat = (float) Booking::where('status', '!=', BookingStatus::Cancelled)->whereIn('customer_id', $repeatIds)->sum('total_amount');

                    return $total ? round($repeat / $total * 100) : 0;
                })(),
            ],
        ];
    }
}; ?>

<div class="space-y-8">
    <x-page-header title="About the system" subtitle="How WanderLink CRM supports the customer lifecycle — the framework, the data and who benefits." />

    {{-- 1. Business --}}
    <section class="card overflow-hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3">
            <div class="bg-brand-800 p-6 text-white lg:p-8">
                <x-application-logo class="size-12" />
                <h2 class="mt-4 text-xl font-bold">WanderLink Travel</h2>
                <p class="mt-2 text-sm text-brand-100">Independent travel agency · 8 staff · one shopfront in Nairobi, plus online enquiry form, WhatsApp and phone.</p>
            </div>
            <div class="grid grid-cols-1 gap-6 p-6 text-sm sm:grid-cols-3 lg:col-span-2 lg:p-8">
                <div><h3 class="font-semibold">Services</h3><p class="mt-1 text-slate-600 dark:text-slate-400">Holiday packages, flights, hotels, safaris & tours, group and corporate travel, visa assistance, travel insurance.</p></div>
                <div><h3 class="font-semibold">Customers</h3><p class="mt-1 text-slate-600 dark:text-slate-400">Leisure travellers, families, small corporate accounts and groups (weddings, church and school trips).</p></div>
                <div><h3 class="font-semibold">Before the CRM</h3><p class="mt-1 text-slate-600 dark:text-slate-400">Enquiries lived in inboxes, WhatsApp and paper diaries. No single customer view, no follow-up discipline; repeat-customer knowledge lived in staff heads.</p></div>
            </div>
        </div>
    </section>

    {{-- 2. Framework diagram --}}
    <x-card title="CRM process framework" subtitle="Acquire → Develop → Serve → Retain → Analyse, looping back to Acquire. Every stage reads and writes the single customer view.">
        <div class="-mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
            <svg viewBox="0 0 1120 470" class="min-w-[860px] w-full" role="img" aria-labelledby="fw-title fw-desc" font-family="Inter, ui-sans-serif, system-ui">
                <title id="fw-title">WanderLink CRM process framework</title>
                <desc id="fw-desc">Five stages in a loop: Acquire (channels to lead capture), Develop (pipeline, quote, booking), Serve (trip support and tickets), Retain (feedback, segmentation, campaigns, re-booking) and Analyse (dashboards and reports), which feeds back into Acquire.</desc>
                <defs>
                    <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 10 5 0 10z" class="fill-slate-400 dark:fill-slate-500" /></marker>
                    <marker id="arrow-sand" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 10 5 0 10z" fill="#F59E0B" /></marker>
                </defs>
                @php($stages = [
                    ['1', 'Acquire', '#0F766E', ['Channels: walk-in, website,', 'WhatsApp, phone, referral', 'Lead capture (enquiry)', 'Assign consultant'], 'Customers · Pipeline'],
                    ['2', 'Develop', '#0284C7', ['Pipeline stages & nudges', 'Quote builder + markup', 'Convert quote → booking', 'Payments (M-Pesa, card)'], 'Pipeline · Quotes · Bookings'],
                    ['3', 'Serve', '#7C3AED', ['Pre-trip documents', 'Departures & passport alerts', 'Trip support', 'Service tickets & SLA'], 'My Day · Tickets'],
                    ['4', 'Retain', '#D97706', ['Post-trip feedback & NPS', 'Lifecycle & segmentation', 'Consent-based campaigns', 'Re-booking reminders'], 'Segments · Campaigns'],
                    ['5', 'Analyse', '#059669', ['Real-time dashboards', 'Scheduled reports', 'Ad-hoc report builder', 'Audit trail'], 'Dashboard · Reports'],
                ])
                @foreach ($stages as $i => [$n, $name, $color, $lines, $modules])
                    @php($x = 20 + $i * 220)
                    <g>
                        <rect x="{{ $x }}" y="40" width="180" height="250" rx="16" class="fill-white stroke-slate-200 dark:fill-slate-900 dark:stroke-slate-700" stroke-width="1.5" />
                        <rect x="{{ $x }}" y="40" width="180" height="58" rx="16" fill="{{ $color }}" />
                        <rect x="{{ $x }}" y="80" width="180" height="18" fill="{{ $color }}" />
                        <circle cx="{{ $x + 28 }}" cy="69" r="14" fill="#fff" fill-opacity="0.25" />
                        <text x="{{ $x + 28 }}" y="74" text-anchor="middle" font-size="14" font-weight="700" fill="#fff">{{ $n }}</text>
                        <text x="{{ $x + 52 }}" y="75" font-size="18" font-weight="700" fill="#fff">{{ $name }}</text>
                        @foreach ($lines as $j => $line)
                            <text x="{{ $x + 16 }}" y="{{ 128 + $j * 26 }}" font-size="12.5" class="fill-slate-700 dark:fill-slate-300">{{ str_starts_with($line, 'WhatsApp') ? '' : '•' }} {{ $line }}</text>
                        @endforeach
                        <line x1="{{ $x + 16 }}" y1="244" x2="{{ $x + 164 }}" y2="244" class="stroke-slate-100 dark:stroke-slate-800" />
                        <text x="{{ $x + 16 }}" y="268" font-size="11" font-weight="600" fill="{{ $color }}">{{ $modules }}</text>
                    </g>
                    @if ($i < 4)
                        <line x1="{{ $x + 184 }}" y1="165" x2="{{ $x + 216 }}" y2="165" class="stroke-slate-400 dark:stroke-slate-500" stroke-width="2" marker-end="url(#arrow)" />
                    @endif
                @endforeach
                {{-- Loop back --}}
                <path d="M990 292 V345 Q990 360 975 360 H125 Q110 360 110 345 V296" fill="none" stroke="#F59E0B" stroke-width="2.5" stroke-dasharray="7 5" marker-end="url(#arrow-sand)" />
                <rect x="400" y="346" width="320" height="28" rx="14" fill="#FEF3C7" />
                <text x="560" y="365" text-anchor="middle" font-size="12.5" font-weight="600" fill="#92400E">Insights and re-bookings feed the next cycle</text>
                {{-- Hub --}}
                <rect x="260" y="398" width="600" height="52" rx="26" class="fill-slate-900 dark:fill-slate-100" />
                <text x="560" y="421" text-anchor="middle" font-size="14" font-weight="700" class="fill-white dark:fill-slate-900">Single customer view</text>
                <text x="560" y="439" text-anchor="middle" font-size="11.5" class="fill-slate-300 dark:fill-slate-600">customers · preferences · interactions · enquiries · bookings · tickets · feedback · consent</text>
            </svg>
        </div>
    </x-card>

    {{-- 3. Why customer information matters --}}
    <section>
        <h2 class="mb-3 text-lg font-bold">Why customer information matters — in this data set</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat label="Customers in one place" :value="number_format($stats['customers'])" icon="users" hint="Replaces inboxes, WhatsApp and paper diaries" />
            <x-stat label="Conversations remembered" :value="number_format($stats['interactions'])" icon="chat-bubble-left-right" tone="sky" hint="Any consultant can pick up the thread" />
            <x-stat label="Customers who returned" :value="$stats['repeatShare'].'%'" icon="arrow-path" tone="emerald" hint="Of customers who have booked" />
            <x-stat label="Revenue from repeat customers" :value="$stats['repeatRevenueShare'].'%'" icon="banknotes" tone="amber" hint="Why retention pays" />
        </div>
    </section>

    {{-- 4. Data sources & types --}}
    <x-card title="Data sources and data types" subtitle="Generated live from the application's models and database schema" :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Data source</th><th>Origin</th><th>Captured via</th><th>Data type</th><th>Examples of content</th><th class="text-right">Records</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($catalog as $row)
                        <tr>
                            <td><p class="font-semibold text-slate-900 dark:text-white">{{ $row['entity'] }}</p><p class="font-mono text-[11px] text-slate-500">{{ $row['table'] }} · {{ $row['fields'] }} fields</p></td>
                            <td class="text-xs"><x-badge :color="str_contains($row['origin'], 'External') ? 'violet' : (str_contains($row['origin'], 'Customer') ? 'sky' : 'teal')" :icon="str_contains($row['origin'], 'External') ? 'globe-alt' : 'building-office'" :label="$row['origin']" size="xs" /></td>
                            <td class="text-xs">{{ $row['channel'] }}</td>
                            <td>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($row['types'] as $type)
                                        <x-badge :color="['Structured' => 'teal', 'Semi-structured' => 'amber', 'Unstructured' => 'rose'][$type]" :icon="['Structured' => 'table-cells', 'Semi-structured' => 'code-bracket', 'Unstructured' => 'document-text'][$type]" :label="$type" size="xs" />
                                    @endforeach
                                </div>
                                @if ($row['json_fields'] || $row['text_fields'])<p class="mt-1 font-mono text-[10px] text-slate-500">{{ implode(', ', array_merge($row['json_fields'], $row['text_fields'])) }}</p>@endif
                            </td>
                            <td class="text-xs">{{ $row['description'] }}@if ($row['sensitive'])<br><span class="text-rose-700 dark:text-rose-400">Personal data: {{ implode(', ', $row['sensitive']) }}</span>@endif</td>
                            <td class="text-right tabular-nums">{{ number_format($row['rows']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="grid grid-cols-1 gap-4 border-t border-slate-100 p-4 text-xs text-slate-600 sm:grid-cols-3 dark:border-slate-800 dark:text-slate-400">
            <p><strong class="text-slate-900 dark:text-white">Structured</strong> — typed columns (names, dates, amounts, statuses) that can be filtered, summed and charted.</p>
            <p><strong class="text-slate-900 dark:text-white">Semi-structured</strong> — JSON documents such as segment rules, preferred airlines and report recipients.</p>
            <p><strong class="text-slate-900 dark:text-white">Unstructured</strong> — free text such as call notes, email bodies, feedback comments and ticket descriptions.</p>
        </div>
    </x-card>

    {{-- 5. Stakeholder benefits --}}
    <x-card title="Stakeholder benefits matrix" :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Stakeholder</th><th>Key benefits</th><th>Where in the system</th><th>Measured by</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ([
                        ['Owner (David)', 'amber', 'key', 'Revenue, pipeline and conversion at a glance; campaign ROI; staff performance; data-driven decisions', 'Executive dashboard, leaderboard, campaign results, scheduled reports', 'Revenue growth, conversion rate, ROI'],
                        ['Operations manager', 'violet', 'briefcase', 'Assign and rebalance leads; monitor SLAs; approve discounts; audit trail for accountability', 'Pipeline (whole team), ticket inbox, quote approvals, audit trail', 'SLA compliance, response time'],
                        ['Travel consultants (Achieng)', 'teal', 'user', 'One view of every customer; never miss a follow-up; faster quotes; fewer lost deals', 'Customer 360, My Day, Kanban with nudges, quote builder', 'Tasks completed, win rate, repeat bookings'],
                        ['Marketing officer', 'rose', 'megaphone', 'Target the right customers; personalised consent-based campaigns; measurable results', 'Segment builder, campaign composer, feedback analysis', 'Open rate, attributed bookings, NPS'],
                        ['Customer service (Grace)', 'sky', 'lifebuoy', 'Full trip context during a crisis; SLA countdowns; resolution history', 'Ticket inbox & detail, customer timeline', 'Tickets resolved within SLA, detractor recovery'],
                        ['Customers', 'emerald', 'heart', 'Personal service, faster answers, remembered preferences, timely reminders, control of their data', 'Preferences, passport alerts, feedback form, data export & erasure', 'NPS, repeat rate'],
                        ['Suppliers', 'indigo', 'building-office-2', 'Accurate, consolidated bookings; volume visibility for negotiating commission', 'Supplier directory with booking volume & margin', 'Bookings per supplier, commission earned'],
                    ] as [$who, $color, $icon, $benefit, $where, $metric])
                        <tr>
                            <td><x-badge :color="$color" :icon="$icon" :label="$who" /></td>
                            <td class="text-sm">{{ $benefit }}</td>
                            <td class="text-xs">{{ $where }}</td>
                            <td class="text-xs">{{ $metric }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    {{-- 6. Governance --}}
    <x-card title="Governance: Murray's criteria and privacy law">
        <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['Security', 'shield-check', 'Role-based permissions (spatie), row-level visibility for consultants, encrypted passport numbers, CSRF on every form, rate-limited login, deactivatable accounts.'],
                ['Accountability', 'finger-print', 'Activity log of who changed what and when; consent changes timestamped; passport views audited.'],
                ['Backup', 'circle-stack', 'crm:backup Artisan command, nightly schedule and an owner-only backup screen with downloads.'],
                ['Ease of use', 'hand-thumb-up', 'Role-aware navigation, Ctrl/⌘+K search, slide-overs, toasts with undo, teaching empty states, dark mode, mobile layouts.'],
                ['Privacy', 'lock-closed', 'Consent enforced in code for campaigns; JSON subject-access export; soft-delete-then-anonymise. Kenya DPA 2019 & Australian Privacy Principles.'],
            ] as [$title, $icon, $text])
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                    <x-hicon :name="$icon" class="size-6 text-brand-700 dark:text-brand-400" />
                    <h3 class="mt-2 font-semibold">{{ $title }}</h3>
                    <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </x-card>
</div>
