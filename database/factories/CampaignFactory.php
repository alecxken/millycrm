<?php

namespace Database\Factories;

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\Campaign> */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Test campaign',
            'segment_id' => Segment::factory(),
            'channel' => CampaignChannel::Email,
            'subject' => 'Hello {{first_name}}',
            'body' => 'Hi {{first_name}}, how was {{last_destination}}?',
            'status' => CampaignStatus::Draft,
            'cost' => 5000,
        ];
    }
}
