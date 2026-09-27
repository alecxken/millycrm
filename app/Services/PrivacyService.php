<?php

namespace App\Services;

use App\Enums\Direction;
use App\Enums\InteractionType;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * Data-subject rights (Kenya Data Protection Act 2019 ss.26, 40;
 * Australian Privacy Principles 12 and 13).
 */
class PrivacyService
{
    /** Subject-access export: everything we hold about the person. */
    public function export(Customer $customer): array
    {
        $customer->loadMissing(['preference', 'contacts', 'tags', 'enquiries.quotes.items', 'bookings.payments', 'interactions', 'tickets.notes', 'feedback', 'consultant:id,name,email']);

        return [
            'exported_at' => now()->toIso8601String(),
            'controller' => 'WanderLink Travel',
            'legal_basis' => 'Kenya Data Protection Act 2019 s.26 (right of access); Australian Privacy Principle 12',
            'customer' => collect($customer->toArray())->except(['preference', 'contacts', 'tags', 'enquiries', 'bookings', 'interactions', 'tickets', 'feedback', 'consultant'])
                ->put('passport_number', $customer->passport_number)
                ->all(),
            'assigned_consultant' => $customer->consultant?->only(['name', 'email']),
            'preferences' => $customer->preference?->toArray(),
            'contacts' => $customer->contacts->toArray(),
            'tags' => $customer->tags->pluck('name'),
            'enquiries' => $customer->enquiries->toArray(),
            'bookings' => $customer->bookings->toArray(),
            'interactions' => $customer->interactions->toArray(),
            'service_tickets' => $customer->tickets->toArray(),
            'feedback' => $customer->feedback->toArray(),
        ];
    }

    /**
     * Soft-delete then anonymise: keeps financial records (bookings, payments)
     * for tax/audit obligations but removes everything that identifies the person.
     */
    public function anonymise(Customer $customer): Customer
    {
        return DB::transaction(function () use ($customer) {
            $customer->interactions()->create([
                'user_id' => auth()->id(),
                'type' => InteractionType::Note,
                'direction' => Direction::Outbound,
                'subject' => 'Personal data anonymised on request',
                'occurred_at' => now(),
            ]);

            $customer->forceFill([
                'first_name' => 'Anonymised',
                'last_name' => 'Customer #'.$customer->id,
                'company_name' => null,
                'email' => null,
                'phone' => null,
                'whatsapp' => null,
                'date_of_birth' => null,
                'passport_number' => null,
                'passport_expiry' => null,
                'city' => null,
                'notes' => null,
                'marketing_consent' => false,
                'consent_at' => null,
                'anonymised_at' => now(),
            ])->save();

            $customer->preference()->delete();
            $customer->contacts()->delete();
            $customer->tags()->detach();
            $customer->interactions()->where('subject', '!=', 'Personal data anonymised on request')->update(['body' => null]);
            $customer->feedback()->update(['comment' => null]);

            $customer->delete(); // soft delete

            return $customer;
        });
    }
}
