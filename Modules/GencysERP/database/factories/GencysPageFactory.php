<?php

namespace Modules\GencysERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\GencysERP\Models\GencysPage;

/**
 * @extends Factory<GencysPage>
 */
class GencysPageFactory extends Factory
{
    protected $model = GencysPage::class;

    public function definition(): array
    {
        return [
            'page_id' => $this->faker->unique()->numberBetween(1, 100000),
            'date_created' => $this->faker->dateTimeBetween('-1 year'),
            'name' => $this->faker->company().' PH',
            'owner' => $this->faker->name(),
            'intern_and_brand' => $this->faker->name().' - '.$this->faker->company(),
            'status' => $this->faker->randomElement(['Active', 'Inactive']),
            'platform' => $this->faker->randomElement(['Facebook', 'TikTok']),
        ];
    }
}
