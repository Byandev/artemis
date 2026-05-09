<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicket>
 */
class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'category' => $this->faker->randomElement(SupportTicket::CATEGORIES),
            'subject' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(2),
            'current_url' => $this->faker->url(),
            'user_agent' => $this->faker->userAgent(),
            'status' => $this->faker->randomElement(SupportTicket::STATUSES),
        ];
    }
}
