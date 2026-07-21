<?php

namespace Modules\SimGateway\Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\SimGateway\Enums\MessageDirection;
use Modules\SimGateway\Enums\MessageStatus;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;

/**
 * @extends Factory<SmsMessage>
 */
class SmsMessageFactory extends Factory
{
    protected $model = SmsMessage::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'sim_id' => Sim::factory(),
            'direction' => MessageDirection::Outbound->value,
            'from_number' => '09'.$this->faker->numerify('#########'),
            'to_number' => '09'.$this->faker->numerify('#########'),
            'message' => $this->faker->sentence(),
            'segments' => 1,
            'status' => MessageStatus::Queued->value,
            'provider_message_id' => 'stub_'.$this->faker->uuid(),
            'error_message' => null,
            'sent_at' => null,
            'delivered_at' => null,
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn (): array => [
            'direction' => MessageDirection::Inbound->value,
            'status' => MessageStatus::Received->value,
        ]);
    }
}
