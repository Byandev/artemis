<?php

namespace Modules\SimGateway\Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SimGateway\Enums\ScheduledMessageStatus;
use Modules\SimGateway\Models\ScheduledMessage;
use Modules\SimGateway\Models\Sim;

/**
 * @extends Factory<ScheduledMessage>
 */
class ScheduledMessageFactory extends Factory
{
    protected $model = ScheduledMessage::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'sim_id' => Sim::factory(),
            'to_number' => '09'.$this->faker->numerify('#########'),
            'message' => $this->faker->sentence(),
            'scheduled_at' => now()->addHour(),
            'status' => ScheduledMessageStatus::Pending->value,
            'sent_at' => null,
            'sms_message_id' => null,
        ];
    }
}
