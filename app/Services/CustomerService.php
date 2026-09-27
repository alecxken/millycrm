<?php

namespace App\Services;

use App\Enums\LifecycleStage;
use App\Models\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public const PREFERENCE_FIELDS = ['seat_preference', 'meal_preference', 'budget_band', 'travel_style', 'special_needs'];

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create([
                ...Arr::except($data, self::PREFERENCE_FIELDS),
                'lifecycle_stage' => LifecycleStage::Lead,
                'consent_at' => ! empty($data['marketing_consent']) ? now() : null,
                'assigned_to' => $data['assigned_to'] ?? auth()->id(),
            ]);

            $customer->preference()->create(Arr::only($data, self::PREFERENCE_FIELDS));

            return $customer;
        });
    }

    public function update(Customer $customer, array $data): Customer
    {
        $consent = (bool) ($data['marketing_consent'] ?? $customer->marketing_consent);

        // Consent is recorded with a timestamp whenever it changes (accountability).
        if ($consent !== $customer->marketing_consent) {
            $data['consent_at'] = $consent ? now() : null;
        }

        $customer->update($data);

        return $customer;
    }
}
