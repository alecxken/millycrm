<?php

namespace App\Services;

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Enums\Direction;
use App\Enums\InteractionType;
use App\Models\Campaign;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Campaigns only ever reach customers who have given marketing consent.
 * This is enforced here, in code, regardless of how the segment is defined
 * (Kenya Data Protection Act 2019 s.26/s.37; Australian Privacy Principle 7).
 */
class CampaignService
{
    public const MERGE_FIELDS = [
        '{{first_name}}' => 'Customer first name',
        '{{last_name}}' => 'Customer last name',
        '{{last_destination}}' => 'Most recent trip destination',
        '{{consultant_name}}' => 'Assigned consultant',
    ];

    public function __construct(private readonly SegmentService $segments) {}

    /** The audience: segment rules AND marketing consent, always. */
    public function audience(Campaign $campaign): Builder
    {
        $rules = $campaign->segment?->rules ?? [];

        return $this->segments->query($rules)->consented();
    }

    public function audienceCount(Campaign $campaign): int
    {
        return $this->audience($campaign)->count();
    }

    public function render(string $template, Customer $customer): string
    {
        $lastDestination = $customer->relationLoaded('bookings')
            ? $customer->bookings->sortByDesc('start_date')->first()?->destination
            : $customer->bookings()->latest('start_date')->value('destination');

        return strtr($template, [
            '{{first_name}}' => $customer->first_name,
            '{{last_name}}' => $customer->last_name,
            '{{last_destination}}' => $lastDestination ?? 'your next adventure',
            '{{consultant_name}}' => $customer->consultant?->name ?? 'the WanderLink team',
        ]);
    }

    /**
     * Simulated send: records recipients, logs an interaction on every
     * customer's timeline and simulates opens. No real messages leave the system.
     */
    public function send(Campaign $campaign): Campaign
    {
        if ($campaign->status === CampaignStatus::Sent) {
            throw new RuntimeException('This campaign has already been sent.');
        }

        return DB::transaction(function () use ($campaign) {
            $recipients = $this->audience($campaign)->with(['bookings:id,customer_id,destination,start_date', 'consultant:id,name'])->get();

            // Defence in depth: never message a customer without consent.
            $recipients = $recipients->filter(fn (Customer $c) => $c->marketing_consent === true);

            $type = match ($campaign->channel) {
                CampaignChannel::Email => InteractionType::Email,
                CampaignChannel::Sms => InteractionType::Sms,
                CampaignChannel::WhatsApp => InteractionType::WhatsApp,
            };

            $openRate = match ($campaign->channel) {
                CampaignChannel::Email => 0.42,
                CampaignChannel::Sms => 0.78,
                CampaignChannel::WhatsApp => 0.86,
            };

            $opens = 0;
            $now = now();

            foreach ($recipients as $customer) {
                // Deterministic "open" simulation so demos are repeatable.
                $opened = (crc32($campaign->id.'-'.$customer->id) % 100) < $openRate * 100;
                $opens += (int) $opened;

                $campaign->recipients()->attach($customer->id, ['opened_at' => $opened ? $now->copy()->addHours(crc32((string) $customer->id) % 48) : null]);

                $customer->interactions()->create([
                    'user_id' => auth()->id(),
                    'type' => $type,
                    'direction' => Direction::Outbound,
                    'subject' => $campaign->subject ? $this->render($campaign->subject, $customer) : "Campaign: {$campaign->name}",
                    'body' => $this->render($campaign->body, $customer),
                    'occurred_at' => $now,
                    'related_type' => Campaign::class,
                    'related_id' => $campaign->id,
                ]);
            }

            $campaign->update([
                'status' => CampaignStatus::Sent,
                'sent_at' => $now,
                'recipients_count' => $recipients->count(),
                'opens' => $opens,
            ]);

            return $campaign->refresh();
        });
    }

    /** @return array{revenue: float, conversions: int, roi: float|null, open_rate: float, conversion_rate: float} */
    public function results(Campaign $campaign): array
    {
        $bookings = $campaign->bookings()->get(['total_amount', 'currency']);
        $revenue = (float) $bookings->sum(fn ($b) => $b->total_amount * $b->currency->toKes());
        $conversions = $bookings->count();
        $cost = (float) $campaign->cost;

        return [
            'revenue' => $revenue,
            'conversions' => $conversions,
            'roi' => $cost > 0 ? round(($revenue * 0.12 - $cost) / $cost * 100, 1) : null, // 12% average agency margin
            'open_rate' => $campaign->open_rate,
            'conversion_rate' => $campaign->recipients_count > 0 ? round($conversions / $campaign->recipients_count * 100, 1) : 0,
        ];
    }
}
