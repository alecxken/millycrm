<?php

use App\Enums\BookingStatus;
use App\Enums\EnquiryStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Feedback;
use App\Models\Segment;
use App\Models\ServiceTicket;
use App\Models\Task;
use App\Services\CampaignService;
use App\Services\ReportService;
use App\Services\SegmentService;
use App\Support\ChartPalette;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Dashboard')] class extends Component
{
    /** Owners/managers can preview each role's dashboard. */
    #[Url(as: 'view')]
    public string $view = '';

    public function mount(): void
    {
        $user = auth()->user();
        $default = match (true) {
            $user->isManagerOrAbove() => 'executive',
            $user->hasRole(Role::Consultant->value) => 'consultant',
            $user->hasRole(Role::Marketing->value) => 'marketing',
            default => 'support',
        };

        if (! $user->isManagerOrAbove() || ! in_array($this->view, ['executive', 'consultant', 'marketing', 'support'], true)) {
            $this->view = $default;
        }
    }

    public function with(ReportService $reports, SegmentService $segments, CampaignService $campaigns): array
    {
        $user = auth()->user();

        return match ($this->view) {
            'executive' => $this->executive($reports),
            'consultant' => $this->consultant($reports, $user),
            'marketing' => $this->marketing($reports, $segments, $campaigns),
            default => $this->support($reports),
        };
    }

    private function executive(ReportService $reports): array
    {
        $funnel = $reports->funnel();
        $destinations = $reports->topDestinations();

        return [
            'kpis' => $reports->kpis(),
            'funnelChart' => ChartPalette::bar($funnel, 'Enquiries', ChartPalette::series(4), horizontal: true),
            'funnel' => $funnel,
            'revenueChart' => ChartPalette::line($reports->revenueTrend(), 'Revenue'),
            'destinationChart' => ChartPalette::bar(array_map(fn ($d) => round($d['revenue']), $destinations), 'Revenue', ChartPalette::SAND, horizontal: true, currency: true),
            'sourceChart' => ChartPalette::doughnut($reports->leadsBySource()),
            'leaderboard' => $reports->leaderboard(),
        ];
    }

    private function consultant(ReportService $reports, $user): array
    {
        // Managers previewing the consultant view see Achieng-style data for the whole team.
        $scope = $user->hasRole(Role::Consultant->value) ? $user : null;
        $kpis = $reports->kpis($scope);

        $byStage = $reports->enquiriesQuery($scope)->open()->get(['status', 'expected_value'])
            ->groupBy(fn ($e) => $e->status->label())
            ->map(fn ($g) => round($g->sum('expected_value')));
        $byStage = collect(EnquiryStatus::cases())->filter->isOpen()->mapWithKeys(fn ($s) => [$s->label() => $byStage[$s->label()] ?? 0])->all();

        return [
            'kpis' => $kpis,
            'pipelineChart' => ChartPalette::bar($byStage, 'Pipeline value', ChartPalette::series(4), currency: true),
            'tasks' => Task::pending()->when($scope, fn ($q) => $q->where('assigned_to', $scope->id))
                ->where('due_at', '<=', now()->endOfDay())->with('customer:id,first_name,last_name,company_name,type')
                ->orderBy('due_at')->limit(6)->get(),
            'tasksDue' => Task::pending()->when($scope, fn ($q) => $q->where('assigned_to', $scope->id))->where('due_at', '<=', now()->endOfDay())->count(),
            'stale' => $reports->enquiriesQuery($scope)->open()->where('last_activity_at', '<=', now()->subDays(Enquiry::STALE_AFTER_DAYS))
                ->with('customer:id,first_name,last_name,company_name,type')->orderBy('last_activity_at')->limit(5)->get(),
            'departing' => Booking::query()->when($scope, fn ($q) => $q->where('consultant_id', $scope->id))
                ->where('status', BookingStatus::Confirmed)->whereBetween('start_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
                ->with('customer:id,first_name,last_name,company_name,type')->orderBy('start_date')->get(),
            'myRevenue' => $reports->revenueTrend(6, $scope),
        ];
    }

    private function marketing(ReportService $reports, SegmentService $segments, CampaignService $campaigns): array
    {
        $segmentSizes = Segment::orderBy('name')->get()->mapWithKeys(fn ($s) => [$s->name => $segments->count($s->rules)])->all();
        $sentiment = $reports->feedbackSentiment();
        $consented = Customer::consented()->count();
        $total = Customer::count();

        return [
            'segmentChart' => ChartPalette::bar($segmentSizes, 'Customers', ChartPalette::TEAL, horizontal: true),
            'sentimentChart' => ChartPalette::doughnut($sentiment, ['#10B981', '#94A3B8', '#E11D48']),
            'campaigns' => Campaign::with('segment:id,name')->latest('sent_at')->get()->map(fn ($c) => ['campaign' => $c, 'results' => $campaigns->results($c)]),
            'nps' => $reports->nps(),
            'consentRate' => $total ? round($consented / $total * 100) : 0,
            'consented' => $consented,
            'recentFeedback' => Feedback::with('customer:id,first_name,last_name,company_name,type')->latest('submitted_at')->limit(5)->get(),
            'sentiment' => $sentiment,
        ];
    }

    private function support(ReportService $reports): array
    {
        $open = ServiceTicket::unresolved();

        return [
            'openTickets' => (clone $open)->count(),
            'breaches' => (clone $open)->where('sla_due_at', '<', now())->count(),
            'dueSoon' => (clone $open)->whereBetween('sla_due_at', [now(), now()->addHours(8)])->count(),
            'nps' => $reports->nps(),
            'categoryChart' => ChartPalette::doughnut($reports->ticketsByCategory() ?: ['No open tickets' => 0]),
            'urgent' => (clone $open)->with(['customer:id,first_name,last_name,company_name,type', 'assignee:id,name,avatar_color'])->orderBy('sla_due_at')->limit(6)->get(),
            'detractors' => Feedback::where('nps_score', '<=', 6)->with('customer:id,first_name,last_name,company_name,type')->latest('submitted_at')->limit(4)->get(),
            'resolvedWeek' => ServiceTicket::whereNotNull('resolved_at')->where('resolved_at', '>=', now()->subWeek())->count(),
        ];
    }
}; ?>

<div wire:poll.30s.visible>
    <x-page-header :title="(now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening')).', '.auth()->user()->firstName()"
                   :subtitle="now()->format('l, j F Y').' · live, refreshes every 30 seconds'">
        <x-slot:actions>
            @if (auth()->user()->isManagerOrAbove())
                <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800" role="tablist" aria-label="Dashboard view">
                    @foreach (['executive' => 'Executive', 'consultant' => 'Consultant', 'marketing' => 'Marketing', 'support' => 'Support'] as $key => $label)
                        <button type="button" role="tab" wire:click="$set('view', '{{ $key }}')" aria-selected="{{ $view === $key ? 'true' : 'false' }}"
                                @class(['rounded-lg px-3 py-1.5 font-medium transition', 'bg-white text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white' => $view === $key, 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' => $view !== $key])>{{ $label }}</button>
                    @endforeach
                </div>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div wire:loading.delay.class="opacity-60" class="transition-opacity">
    @if ($view === 'executive')
        {{-- David wants the numbers in 10 seconds. --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <x-stat label="Revenue this month" :value="money($kpis['revenue_month'])" icon="banknotes" :trend="$kpis['revenue_change']" :href="route('bookings.index')" />
            <x-stat label="Open pipeline" :value="money($kpis['pipeline_value'], compact: true)" icon="view-columns" tone="violet" :hint="$kpis['open_enquiries'].' open enquiries · weighted '.money($kpis['pipeline_weighted'], compact: true)" :href="route('pipeline')" />
            <x-stat label="Conversion rate" :value="$kpis['conversion_rate'].'%'" icon="trophy" tone="emerald" hint="Won ÷ closed enquiries, last 12 months" />
            <x-stat label="Average booking value" :value="money($kpis['avg_booking_value'])" icon="ticket" tone="sky" hint="Last 12 months" />
            <x-stat label="Open service tickets" :value="$kpis['open_tickets']" icon="lifebuoy" :tone="$kpis['sla_breaches'] ? 'rose' : 'amber'" :hint="$kpis['sla_breaches'].' breaching SLA now'" :href="route('tickets.index')" />
            <x-stat label="Net Promoter Score" :value="$kpis['nps'] !== null ? ($kpis['nps'] > 0 ? '+' : '').$kpis['nps'] : '—'" icon="heart" tone="amber" hint="Promoters minus detractors, last 12 months" :href="route('feedback.index')" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Revenue trend" subtitle="Confirmed bookings by month, last 12 months" class="lg:col-span-2">
                <x-chart :config="$revenueChart" label="Revenue by month" height="h-72" />
            </x-card>
            <x-card title="Sales funnel" subtitle="Enquiries in the last 12 months">
                <x-chart :config="$funnelChart" label="Sales funnel" height="h-52" />
                <dl class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                    @php($keys = array_keys($funnel))
                    @foreach (array_slice($keys, 1) as $i => $stage)
                        <div class="rounded-lg bg-slate-50 p-2 dark:bg-slate-800/60">
                            <dt class="text-slate-500 dark:text-slate-400">→ {{ $stage }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold">{{ $funnel[$keys[$i]] ? round($funnel[$stage] / $funnel[$keys[$i]] * 100) : 0 }}%</dd>
                        </div>
                    @endforeach
                </dl>
            </x-card>
            <x-card title="Top destinations" subtitle="Revenue, last 12 months" class="lg:col-span-2">
                <x-chart :config="$destinationChart" label="Top destinations by revenue" height="h-72" />
            </x-card>
            <x-card title="Leads by source" subtitle="Where enquiries come from">
                <x-chart :config="$sourceChart" label="Leads by source" height="h-72" />
            </x-card>
        </div>

        <x-card title="Consultant leaderboard" subtitle="Last 12 months" class="mt-6" :padding="false">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>#</th><th>Consultant</th><th class="text-right">Revenue</th><th class="text-right">Won</th><th class="text-right">Open</th><th class="w-48">Conversion</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($leaderboard as $i => $row)
                            <tr>
                                <td class="font-semibold text-slate-400">{{ $i + 1 }}</td>
                                <td><div class="flex items-center gap-3"><x-avatar :user="$row['user']" size="sm" /><span class="font-medium text-slate-900 dark:text-white">{{ $row['user']->name }}</span></div></td>
                                <td class="text-right font-semibold tabular-nums">{{ money($row['revenue']) }}</td>
                                <td class="text-right tabular-nums">{{ $row['won'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['open'] }}</td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-2 rounded-full bg-brand-600" style="width: {{ $row['conversion'] }}%"></div></div>
                                        <span class="w-10 text-right text-xs font-semibold tabular-nums">{{ $row['conversion'] }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

    @elseif ($view === 'consultant')
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-stat label="My pipeline" :value="money($kpis['pipeline_value'], compact: true)" icon="view-columns" :hint="$kpis['open_enquiries'].' open enquiries'" :href="route('pipeline')" />
            <x-stat label="My conversion" :value="$kpis['conversion_rate'].'%'" icon="trophy" tone="emerald" hint="Last 12 months" />
            <x-stat label="Booked this month" :value="money($kpis['revenue_month'], compact: true)" icon="banknotes" tone="sky" :trend="$kpis['revenue_change']" />
            <x-stat label="Tasks due today" :value="$tasksDue" icon="clipboard-document-check" :tone="$tasksDue ? 'amber' : 'teal'" hint="Including overdue" :href="route('my-day')" />
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="My pipeline by stage" subtitle="Expected value of open enquiries" class="lg:col-span-2">
                <x-chart :config="$pipelineChart" label="Pipeline by stage" height="h-64" />
            </x-card>
            <x-card title="Due today" :padding="false">
                <x-slot:actions><a href="{{ route('my-day') }}" wire:navigate class="link text-xs">Open My Day →</a></x-slot:actions>
                @forelse ($tasks as $task)
                    <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
                        <x-hicon :name="$task->type->icon()" @class(['mt-0.5 size-5', 'text-rose-600' => $task->isOverdue(), 'text-slate-400' => ! $task->isOverdue()]) />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $task->title }}</p>
                            <p @class(['text-xs', 'font-semibold text-rose-700 dark:text-rose-400' => $task->isOverdue(), 'text-slate-500' => ! $task->isOverdue()])>
                                {{ $task->isOverdue() ? 'Overdue · '.$task->due_at->diffForHumans() : $task->due_at->format('H:i') }}
                            </p>
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="check-badge" title="All clear" description="Nothing due today. A good moment to call a lapsed customer." />
                @endforelse
            </x-card>
            <x-card title="Going quiet" subtitle="No activity for {{ \App\Models\Enquiry::STALE_AFTER_DAYS }}+ days" :padding="false" class="lg:col-span-2">
                @forelse ($stale as $enquiry)
                    <a href="{{ route('pipeline', ['enquiry' => $enquiry->id]) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <x-hicon name="clock" class="size-5 text-amber-600" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $enquiry->customer->display_name }} · {{ $enquiry->destination }}</p>
                            <p class="text-xs text-slate-500">Last touch {{ $enquiry->last_activity_at?->diffForHumans() }} · {{ money($enquiry->expected_value) }}</p>
                        </div>
                        <x-badge :enum="$enquiry->status" />
                    </a>
                @empty
                    <x-empty-state icon="bolt" title="Nothing is going cold" description="Every open enquiry has been touched recently. Nice work." />
                @endforelse
            </x-card>
            <x-card title="Departing this week" :padding="false">
                @forelse ($departing as $booking)
                    <a href="{{ route('bookings.show', $booking) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <x-hicon name="paper-airplane" class="size-5 text-sky-600" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $booking->customer->display_name }}</p>
                            <p class="text-xs text-slate-500">{{ $booking->destination }} · {{ fdate($booking->start_date) }}</p>
                        </div>
                    </a>
                @empty
                    <x-empty-state icon="paper-airplane" title="No departures this week" />
                @endforelse
            </x-card>
        </div>

    @elseif ($view === 'marketing')
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-stat label="Marketable audience" :value="number_format($consented)" icon="check-badge" :hint="$consentRate.'% of customers have opted in'" />
            <x-stat label="Net Promoter Score" :value="$nps !== null ? ($nps > 0 ? '+' : '').$nps : '—'" icon="heart" tone="amber" hint="Last 12 months" />
            <x-stat label="Campaigns sent" :value="$campaigns->where('campaign.status', \App\Enums\CampaignStatus::Sent)->count()" icon="megaphone" tone="violet" :href="route('campaigns.index')" />
            <x-stat label="Attributed bookings" :value="$campaigns->sum('results.conversions')" icon="ticket" tone="emerald" :hint="money($campaigns->sum('results.revenue'), compact: true).' revenue'" />
        </div>
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Segment sizes" subtitle="Customers matching each segment's rules" class="lg:col-span-2">
                <x-chart :config="$segmentChart" label="Segment sizes" height="h-72" />
            </x-card>
            <x-card title="Feedback sentiment" subtitle="NPS groups, last 12 months">
                <x-chart :config="$sentimentChart" label="Feedback sentiment" height="h-72" />
            </x-card>
        </div>
        <x-card title="Campaign performance" class="mt-6" :padding="false">
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Campaign</th><th>Status</th><th class="text-right">Recipients</th><th class="text-right">Open rate</th><th class="text-right">Bookings</th><th class="text-right">ROI</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($campaigns as $row)
                            <tr>
                                <td><a href="{{ route('campaigns.show', $row['campaign']) }}" wire:navigate class="link">{{ $row['campaign']->name }}</a><div class="text-xs text-slate-500">{{ $row['campaign']->segment?->name }}</div></td>
                                <td><x-badge :enum="$row['campaign']->status" /></td>
                                <td class="text-right tabular-nums">{{ $row['campaign']->recipients_count }}</td>
                                <td class="text-right tabular-nums">{{ $row['results']['open_rate'] }}%</td>
                                <td class="text-right tabular-nums">{{ $row['results']['conversions'] }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ $row['results']['roi'] !== null && $row['campaign']->recipients_count ? $row['results']['roi'].'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

    @else
        {{-- Grace: what's on fire right now? --}}
        <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <x-stat label="Open tickets" :value="$openTickets" icon="inbox" :href="route('tickets.index')" />
            <x-stat label="SLA breached" :value="$breaches" icon="exclamation-triangle" :tone="$breaches ? 'rose' : 'emerald'" hint="Past their response deadline" :href="route('tickets.index', ['filter' => 'breached'])" />
            <x-stat label="Due in 8 hours" :value="$dueSoon" icon="clock" tone="amber" />
            <x-stat label="Resolved this week" :value="$resolvedWeek" icon="check-circle" tone="emerald" :hint="'NPS '.($nps ?? '—')" />
        </div>
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Needs attention first" subtitle="Sorted by SLA deadline" :padding="false" class="lg:col-span-2">
                @forelse ($urgent as $ticket)
                    <a href="{{ route('tickets.show', $ticket) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <x-badge :enum="$ticket->priority" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $ticket->subject }}</p>
                            <p class="text-xs text-slate-500">{{ $ticket->customer->display_name }} · {{ $ticket->reference }}</p>
                        </div>
                        @include('partials.sla', ['ticket' => $ticket])
                    </a>
                @empty
                    <x-empty-state icon="face-smile" title="Inbox zero" description="No open tickets. Enjoy the calm." />
                @endforelse
            </x-card>
            <x-card title="Open tickets by category">
                <x-chart :config="$categoryChart" label="Open tickets by category" height="h-64" />
            </x-card>
        </div>
        <x-card title="Recent detractors" subtitle="Customers who scored us 0–6 — call them back" class="mt-6" :padding="false">
            @forelse ($detractors as $fb)
                <div class="flex items-start gap-3 border-b border-slate-100 px-4 py-3 last:border-0 dark:border-slate-800">
                    <x-badge color="rose" icon="face-frown" :label="'NPS '.$fb->nps_score" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm">“{{ $fb->comment }}”</p>
                        <p class="text-xs text-slate-500">{{ $fb->customer->display_name }} · {{ fdate($fb->submitted_at) }}</p>
                    </div>
                </div>
            @empty
                <x-empty-state icon="face-smile" title="No detractors recently" />
            @endforelse
        </x-card>
    @endif
    </div>
</div>
