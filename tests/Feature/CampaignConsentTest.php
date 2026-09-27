<?php

use App\Enums\CampaignStatus;
use App\Enums\LifecycleStage;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Interaction;
use App\Models\Segment;
use App\Services\CampaignService;

it('only sends campaigns to customers with marketing consent', function () {
    $consented = Customer::factory()->count(3)->consented()->stage(LifecycleStage::Repeat)->create();
    $refused = Customer::factory()->count(2)->consented(false)->stage(LifecycleStage::Repeat)->create();

    // Even a segment that explicitly targets non-consented customers must not reach them.
    $segment = Segment::factory()->create(['rules' => ['lifecycle_stage' => ['repeat']]]);
    $campaign = Campaign::factory()->for($segment)->create();

    app(CampaignService::class)->send($campaign);

    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->recipients_count)->toBe(3)
        ->and($campaign->recipients()->pluck('customers.id')->sort()->values()->all())->toBe($consented->pluck('id')->sort()->values()->all());

    foreach ($refused as $customer) {
        expect(Interaction::where('customer_id', $customer->id)->where('related_type', Campaign::class)->exists())->toBeFalse();
    }
});

it('refuses a segment that asks for non-consented customers', function () {
    Customer::factory()->count(2)->consented(false)->create();
    $segment = Segment::factory()->create(['rules' => ['marketing_consent' => false]]);
    $campaign = Campaign::factory()->for($segment)->create();

    expect(app(CampaignService::class)->audienceCount($campaign))->toBe(0);
});

it('personalises messages with merge fields and logs them on the timeline', function () {
    $customer = Customer::factory()->consented()->create(['first_name' => 'Wanjiku']);
    \App\Models\Booking::factory()->for($customer)->create(['destination' => 'Lamu']);
    $campaign = Campaign::factory()->for(Segment::factory()->create(['rules' => []]))->create(['body' => 'Hi {{first_name}}, back to {{last_destination}}?']);

    app(CampaignService::class)->send($campaign);

    expect(Interaction::where('customer_id', $customer->id)->where('related_type', Campaign::class)->value('body'))
        ->toBe('Hi Wanjiku, back to Lamu?');
});

it('cannot send the same campaign twice', function () {
    $campaign = Campaign::factory()->for(Segment::factory()->create())->create();
    app(CampaignService::class)->send($campaign);

    expect(fn () => app(CampaignService::class)->send($campaign->fresh()))->toThrow(RuntimeException::class);
});
