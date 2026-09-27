<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns a JSON rule set into a customer query. Rules combine with AND;
 * list values within a rule combine with OR.
 *
 * Example: {"lifecycle_stage":["repeat","vip"],"travel_style":["safari"],"marketing_consent":true}
 */
class SegmentService
{
    /** Rule keys the builder understands, with a human label. */
    public const RULES = [
        'lifecycle_stage' => 'Lifecycle stage',
        'type' => 'Customer type',
        'source' => 'Acquisition source',
        'travel_style' => 'Travel style',
        'budget_band' => 'Budget band',
        'country' => 'Country',
        'tags' => 'Tags',
        'marketing_consent' => 'Marketing consent',
        'min_bookings' => 'Minimum bookings',
        'min_lifetime_value' => 'Minimum lifetime value (KES)',
        'travelled_within_months' => 'Travelled within (months)',
        'not_travelled_within_months' => 'Not travelled for (months)',
        'birthday_month' => 'Birthday month',
    ];

    public function query(array $rules): Builder
    {
        $query = Customer::query()->whereNull('anonymised_at');
        $rules = $this->normalise($rules);

        foreach (['lifecycle_stage', 'type', 'source', 'country'] as $column) {
            if (! empty($rules[$column])) {
                $query->whereIn($column, (array) $rules[$column]);
            }
        }

        foreach (['travel_style', 'budget_band'] as $column) {
            if (! empty($rules[$column])) {
                $query->whereHas('preference', fn (Builder $q) => $q->whereIn($column, (array) $rules[$column]));
            }
        }

        if (! empty($rules['tags'])) {
            $query->whereHas('tags', fn (Builder $q) => $q->whereIn('tags.id', (array) $rules['tags'])->orWhereIn('tags.name', (array) $rules['tags']));
        }

        if (array_key_exists('marketing_consent', $rules) && $rules['marketing_consent'] !== null) {
            $query->where('marketing_consent', (bool) $rules['marketing_consent']);
        }

        if (! empty($rules['min_bookings'])) {
            $query->whereHas('bookings', fn (Builder $q) => $q->where('status', '!=', BookingStatus::Cancelled), '>=', (int) $rules['min_bookings']);
        }

        if (! empty($rules['min_lifetime_value'])) {
            $query->whereRaw(
                '(select coalesce(sum(total_amount), 0) from bookings where bookings.customer_id = customers.id and bookings.status != ?) >= ?',
                [BookingStatus::Cancelled->value, (int) round((float) $rules['min_lifetime_value'])] // int binding: SQLite compares numbers < strings
            );
        }

        if (! empty($rules['travelled_within_months'])) {
            $query->whereHas('bookings', fn (Builder $q) => $q->where('end_date', '>=', now()->subMonths((int) $rules['travelled_within_months'])));
        }

        if (! empty($rules['not_travelled_within_months'])) {
            $query->whereHas('bookings')
                ->whereDoesntHave('bookings', fn (Builder $q) => $q->where('start_date', '>=', now()->subMonths((int) $rules['not_travelled_within_months'])));
        }

        if (! empty($rules['birthday_month'])) {
            $query->whereNotNull('date_of_birth')
                ->whereRaw(
                    $query->getConnection()->getDriverName() === 'sqlite'
                        ? "CAST(strftime('%m', date_of_birth) AS INTEGER) = ?"
                        : 'MONTH(date_of_birth) = ?',
                    [(int) $rules['birthday_month']]
                );
        }

        return $query;
    }

    public function count(array $rules): int
    {
        return $this->query($rules)->count();
    }

    public function matches(Customer $customer, array $rules): bool
    {
        return $this->query($rules)->whereKey($customer->getKey())->exists();
    }

    /** Human-readable summary, e.g. "Lifecycle stage: Repeat, VIP · Travel style: Safari". */
    public function describe(array $rules): string
    {
        return collect($this->normalise($rules))
            ->map(function ($value, $key) {
                $label = self::RULES[$key] ?? $key;
                $value = is_bool($value) ? ($value ? 'Yes' : 'No') : implode(', ', array_map(fn ($v) => str($v)->replace('_', ' ')->title(), (array) $value));

                return "{$label}: {$value}";
            })
            ->implode(' · ') ?: 'All customers';
    }

    /** Drop empty values so "no rule" never filters everything out. */
    public function normalise(array $rules): array
    {
        return collect($rules)
            ->only(array_keys(self::RULES))
            ->reject(fn ($v) => $v === null || $v === '' || $v === [])
            ->map(fn ($v, $k) => $k === 'marketing_consent' ? filter_var($v, FILTER_VALIDATE_BOOLEAN) : $v)
            ->all();
    }
}
