<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\EnquiryStatus;
use App\Enums\ReportType;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Feedback;
use App\Models\ScheduledReport;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The three MIS report types from the brief:
 *  - real-time (dashboard KPIs and charts)
 *  - scheduled (generate() used by crm:run-scheduled-reports)
 *  - ad-hoc (adHoc() used by the report builder)
 *
 * Grouping is done in PHP so the same code runs on SQLite and MySQL.
 */
class ReportService
{
    public function __construct(private readonly PipelineService $pipeline) {}

    /* ------------------------------------------------------------------ */
    /*  Real-time                                                          */
    /* ------------------------------------------------------------------ */

    public function bookingsQuery(?User $scope = null): Builder
    {
        return Booking::query()->where('status', '!=', BookingStatus::Cancelled)
            ->when($scope, fn ($q) => $q->where('consultant_id', $scope->id));
    }

    public function enquiriesQuery(?User $scope = null): Builder
    {
        return Enquiry::query()->when($scope, fn ($q) => $q->where('assigned_to', $scope->id));
    }

    private function kes(Collection $bookings): float
    {
        return (float) $bookings->sum(fn ($b) => (float) $b->total_amount * $b->currency->toKes());
    }

    /** @return array<string, mixed> */
    public function kpis(?User $scope = null): array
    {
        $monthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $yearAgo = now()->subYear();

        $thisMonth = $this->kes($this->bookingsQuery($scope)->where('created_at', '>=', $monthStart)->get(['total_amount', 'currency']));
        $lastMonth = $this->kes($this->bookingsQuery($scope)->whereBetween('created_at', [$lastMonthStart, $monthStart])->get(['total_amount', 'currency']));

        $open = $this->enquiriesQuery($scope)->open();
        $closed = $this->enquiriesQuery($scope)->where('created_at', '>=', $yearAgo)->whereIn('status', [EnquiryStatus::Won, EnquiryStatus::Lost])->get(['status']);
        $won = $closed->where('status', EnquiryStatus::Won)->count();

        $yearBookings = $this->bookingsQuery($scope)->where('created_at', '>=', $yearAgo)->get(['total_amount', 'currency']);

        return [
            'revenue_month' => $thisMonth,
            'revenue_change' => $lastMonth > 0 ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1) : null,
            'pipeline_value' => (float) (clone $open)->sum('expected_value'),
            'pipeline_weighted' => $this->pipeline->weightedValue($this->enquiriesQuery($scope)),
            'open_enquiries' => (clone $open)->count(),
            'conversion_rate' => $closed->count() > 0 ? round($won / $closed->count() * 100, 1) : 0,
            'avg_booking_value' => $yearBookings->count() > 0 ? $this->kes($yearBookings) / $yearBookings->count() : 0,
            'open_tickets' => ServiceTicket::unresolved()->count(),
            'sla_breaches' => ServiceTicket::unresolved()->where('sla_due_at', '<', now())->count(),
            'nps' => $this->nps(),
        ];
    }

    public function nps(?Carbon $since = null): ?int
    {
        $scores = Feedback::where('submitted_at', '>=', $since ?? now()->subYear())->pluck('nps_score');

        if ($scores->isEmpty()) {
            return null;
        }

        $promoters = $scores->filter(fn ($s) => $s >= 9)->count();
        $detractors = $scores->filter(fn ($s) => $s <= 6)->count();

        return (int) round(($promoters - $detractors) / $scores->count() * 100);
    }

    /** Sales funnel for enquiries created in the last 12 months. */
    public function funnel(?User $scope = null): array
    {
        $enquiries = $this->enquiriesQuery($scope)->where('created_at', '>=', now()->subYear())->withCount('quotes')->get(['id', 'status']);

        return [
            'Enquiries' => $enquiries->count(),
            'Contacted' => $enquiries->where('status', '!=', EnquiryStatus::New)->count(),
            'Quoted' => $enquiries->filter(fn ($e) => $e->quotes_count > 0 || in_array($e->status, [EnquiryStatus::Quoted, EnquiryStatus::Negotiating, EnquiryStatus::Won], true))->count(),
            'Won' => $enquiries->where('status', EnquiryStatus::Won)->count(),
        ];
    }

    /** @return array<string, float> "Oct 25" => revenue */
    public function revenueTrend(int $months = 12, ?User $scope = null): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $bookings = $this->bookingsQuery($scope)->where('created_at', '>=', $start)->get(['total_amount', 'currency', 'created_at']);
        $grouped = $bookings->groupBy(fn ($b) => $b->created_at->format('Y-m'));

        $trend = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $trend[$month->format('M y')] = round($this->kes($grouped->get($month->format('Y-m'), collect())));
        }

        return $trend;
    }

    public function topDestinations(int $limit = 8): array
    {
        return $this->bookingsQuery()->where('created_at', '>=', now()->subYear())->get(['destination', 'total_amount', 'currency'])
            ->groupBy('destination')
            ->map(fn ($g) => ['bookings' => $g->count(), 'revenue' => $this->kes($g)])
            ->sortByDesc('revenue')->take($limit)->all();
    }

    public function leadsBySource(): array
    {
        return Enquiry::where('created_at', '>=', now()->subYear())->get(['channel'])
            ->countBy(fn ($e) => $e->channel->label())->sortDesc()->all();
    }

    /** Consultant leaderboard: revenue, deals won and conversion rate (last 12 months). */
    public function leaderboard(): Collection
    {
        $since = now()->subYear();

        return User::role(Role::Consultant->value)
            ->with([
                'bookings' => fn ($q) => $q->where('status', '!=', BookingStatus::Cancelled)->where('created_at', '>=', $since)->select('id', 'consultant_id', 'total_amount', 'currency'),
                'enquiries' => fn ($q) => $q->where('created_at', '>=', $since)->select('id', 'assigned_to', 'status'),
            ])
            ->get()
            ->map(function (User $user) {
                $closed = $user->enquiries->whereIn('status', [EnquiryStatus::Won, EnquiryStatus::Lost]);
                $won = $closed->where('status', EnquiryStatus::Won)->count();

                return [
                    'user' => $user,
                    'revenue' => $this->kes($user->bookings),
                    'won' => $won,
                    'open' => $user->enquiries->filter(fn ($e) => $e->status->isOpen())->count(),
                    'conversion' => $closed->count() ? round($won / $closed->count() * 100) : 0,
                ];
            })
            ->sortByDesc('revenue')->values();
    }

    public function ticketsByCategory(): array
    {
        return ServiceTicket::unresolved()->get(['category'])->countBy(fn ($t) => $t->category->label())->all();
    }

    public function feedbackSentiment(): array
    {
        $feedback = Feedback::where('submitted_at', '>=', now()->subYear())->get(['nps_score']);

        return [
            'Promoters' => $feedback->filter(fn ($f) => $f->nps_score >= 9)->count(),
            'Passives' => $feedback->filter(fn ($f) => $f->nps_score >= 7 && $f->nps_score <= 8)->count(),
            'Detractors' => $feedback->filter(fn ($f) => $f->nps_score <= 6)->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Ad-hoc                                                             */
    /* ------------------------------------------------------------------ */

    public const ENTITIES = [
        'customers' => [
            'label' => 'Customers',
            'group_by' => ['month' => 'Month created', 'lifecycle_stage' => 'Lifecycle stage', 'source' => 'Source', 'type' => 'Customer type', 'country' => 'Country', 'consultant' => 'Consultant'],
            'filters' => ['lifecycle_stage', 'source', 'type', 'country', 'assigned_to'],
        ],
        'enquiries' => [
            'label' => 'Enquiries',
            'group_by' => ['month' => 'Month created', 'status' => 'Status', 'channel' => 'Channel', 'destination' => 'Destination', 'trip_type' => 'Trip type', 'consultant' => 'Consultant'],
            'filters' => ['status', 'channel', 'trip_type', 'assigned_to'],
        ],
        'bookings' => [
            'label' => 'Bookings',
            'group_by' => ['month' => 'Month booked', 'destination' => 'Destination', 'status' => 'Status', 'payment_status' => 'Payment status', 'consultant' => 'Consultant'],
            'filters' => ['status', 'payment_status', 'consultant_id'],
        ],
    ];

    /**
     * @return array{rows: array<int, array{label:string, count:int, value:float}>, total_count:int, total_value:float}
     */
    public function adHoc(string $entity, string $groupBy, array $filters = [], ?string $from = null, ?string $to = null): array
    {
        abort_unless(isset(self::ENTITIES[$entity]), 422, 'Unknown entity');
        abort_unless(isset(self::ENTITIES[$entity]['group_by'][$groupBy]), 422, 'Unknown grouping');

        $query = match ($entity) {
            'customers' => Customer::query()->with('consultant:id,name')->withSum(['bookings as ltv' => fn ($q) => $q->where('status', '!=', BookingStatus::Cancelled)], 'total_amount'),
            'enquiries' => Enquiry::query()->with('consultant:id,name'),
            'bookings' => Booking::query()->with('consultant:id,name'),
        };

        foreach (array_intersect_key(array_filter($filters, fn ($v) => $v !== null && $v !== ''), array_flip(self::ENTITIES[$entity]['filters'])) as $column => $value) {
            $query->whereIn($column, (array) $value);
        }

        if ($from) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }

        $records = $query->get();

        $valueOf = fn ($r) => match ($entity) {
            'customers' => (float) $r->ltv,
            'enquiries' => (float) $r->expected_value,
            'bookings' => (float) $r->total_amount * $r->currency->toKes(),
        };

        $labelOf = function ($r) use ($groupBy) {
            if ($groupBy === 'month') {
                return $r->created_at->format('Y-m');
            }
            if ($groupBy === 'consultant') {
                return $r->consultant?->name ?? 'Unassigned';
            }
            $value = $r->{$groupBy};

            return $value instanceof \BackedEnum ? $value->label() : ($value ?: '—');
        };

        $rows = $records->groupBy($labelOf)
            ->map(fn ($group, $label) => [
                'label' => $groupBy === 'month' ? Carbon::createFromFormat('Y-m', $label)->format('M Y') : $label,
                'sort' => $label,
                'count' => $group->count(),
                'value' => round($group->sum($valueOf)),
            ]);

        $rows = ($groupBy === 'month' ? $rows->sortBy('sort') : $rows->sortByDesc('count'))->values()
            ->map(fn ($r) => collect($r)->except('sort')->all())->all();

        return [
            'rows' => $rows,
            'total_count' => $records->count(),
            'total_value' => round($records->sum($valueOf)),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Scheduled                                                          */
    /* ------------------------------------------------------------------ */

    /** @return array{title:string, lines: array<string, string>} */
    public function generate(ScheduledReport $report): array
    {
        $period = match ($report->frequency->value) {
            'daily' => now()->subDay(),
            'weekly' => now()->subWeek(),
            default => now()->subMonth(),
        };

        $lines = match ($report->report_type) {
            ReportType::SalesSummary => (function () use ($period) {
                $bookings = $this->bookingsQuery()->where('created_at', '>=', $period)->get(['total_amount', 'currency']);

                return [
                    'New bookings' => (string) $bookings->count(),
                    'Revenue' => money($this->kes($bookings)),
                    'Enquiries received' => (string) Enquiry::where('created_at', '>=', $period)->count(),
                    'Deals won' => (string) Enquiry::where('status', EnquiryStatus::Won)->where('stage_changed_at', '>=', $period)->count(),
                ];
            })(),
            ReportType::Pipeline => collect(EnquiryStatus::cases())
                ->mapWithKeys(fn ($s) => [$s->label() => Enquiry::where('status', $s)->count().' enquiries']) ->all()
                + ['Weighted pipeline' => money($this->pipeline->weightedValue())],
            ReportType::ServiceLevels => [
                'Open tickets' => (string) ServiceTicket::unresolved()->count(),
                'SLA breaches (open)' => (string) ServiceTicket::unresolved()->where('sla_due_at', '<', now())->count(),
                'Resolved in period' => (string) ServiceTicket::where('status', TicketStatus::Resolved)->where('resolved_at', '>=', $period)->count(),
                'NPS (12 months)' => (string) ($this->nps() ?? 'n/a'),
            ],
            ReportType::CustomerGrowth => [
                'New customers/leads' => (string) Customer::where('created_at', '>=', $period)->count(),
                'Total active records' => (string) Customer::count(),
                'With marketing consent' => (string) Customer::consented()->count(),
            ],
            ReportType::AdHoc => collect($this->adHoc(
                $report->parameters['entity'] ?? 'bookings',
                $report->parameters['group_by'] ?? 'month',
                $report->parameters['filters'] ?? [],
                $period->toDateString(),
            )['rows'])->mapWithKeys(fn ($r) => [$r['label'] => $r['count'].' · '.money($r['value'])])->all(),
        };

        return [
            'title' => "{$report->name} ({$report->frequency->label()}) — ".fdate(now()),
            'lines' => $lines,
        ];
    }

    public function renderText(array $output): string
    {
        $width = max(array_map('mb_strlen', array_keys($output['lines'])) ?: [10]);

        return $output['title']."\n".str_repeat('=', mb_strlen($output['title']))."\n"
            .collect($output['lines'])->map(fn ($v, $k) => str_pad($k, $width + 2).$v)->implode("\n")."\n";
    }
}
