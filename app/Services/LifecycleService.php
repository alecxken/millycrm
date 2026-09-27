<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\LifecycleStage;
use App\Models\Customer;
use Illuminate\Support\Carbon;

/**
 * Automatic lifecycle promotion rules (Customer 360):
 *  - lead/prospect -> customer on first booking
 *  - customer -> repeat on second booking
 *  - repeat -> vip at 5+ bookings or lifetime value >= KES 1,000,000
 *  - any stage -> inactive after 18 months with no activity
 */
class LifecycleService
{
    public const VIP_BOOKINGS = 5;

    public const VIP_LIFETIME_VALUE = 1_000_000;

    public const INACTIVE_AFTER_MONTHS = 18;

    public function lifetimeValue(Customer $customer): float
    {
        return (float) $customer->bookings()
            ->where('status', '!=', BookingStatus::Cancelled)
            ->get(['total_amount', 'currency'])
            ->sum(fn ($b) => (float) $b->total_amount * $b->currency->toKes());
    }

    public function bookingCount(Customer $customer): int
    {
        return $customer->bookings()->where('status', '!=', BookingStatus::Cancelled)->count();
    }

    public function lastActivityAt(Customer $customer): Carbon
    {
        return collect([
            $customer->last_contacted_at,
            $customer->bookings()->max('created_at'),
            $customer->bookings()->max('end_date'),
            $customer->enquiries()->max('created_at'),
            $customer->created_at,
        ])->filter()->map(fn ($d) => Carbon::parse($d))->max();
    }

    /** Work out the stage the rules say this customer should be in. */
    public function determineStage(Customer $customer): LifecycleStage
    {
        if ($this->lastActivityAt($customer)->lt(now()->subMonths(self::INACTIVE_AFTER_MONTHS))) {
            return LifecycleStage::Inactive;
        }

        $count = $this->bookingCount($customer);
        $ltv = $this->lifetimeValue($customer);

        $earned = match (true) {
            $count >= self::VIP_BOOKINGS || $ltv >= self::VIP_LIFETIME_VALUE => LifecycleStage::Vip,
            $count >= 2 => LifecycleStage::Repeat,
            $count >= 1 => LifecycleStage::Customer,
            default => LifecycleStage::Lead,
        };

        $current = $customer->lifecycle_stage ?? LifecycleStage::Lead;

        // Returning from inactive: fall back to whatever the booking history earns.
        if ($current === LifecycleStage::Inactive) {
            return $earned;
        }

        // Never demote (e.g. a manually flagged VIP or a qualified prospect stays put).
        return $earned->rank() > $current->rank() ? $earned : $current;
    }

    /** Apply the rules and persist; returns true if the stage changed. */
    public function evaluate(Customer $customer): bool
    {
        $stage = $this->determineStage($customer);

        if ($stage === $customer->lifecycle_stage) {
            return false;
        }

        $customer->update(['lifecycle_stage' => $stage]);

        return true;
    }

    /** Nightly sweep used by crm:lifecycle-sweep. Returns number of changes. */
    public function sweep(): int
    {
        $changed = 0;

        Customer::query()->whereNull('anonymised_at')->chunkById(200, function ($customers) use (&$changed) {
            foreach ($customers as $customer) {
                $changed += (int) $this->evaluate($customer);
            }
        });

        return $changed;
    }
}
