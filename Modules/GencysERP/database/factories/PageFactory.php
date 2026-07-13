<?php

namespace Modules\GencysERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\GencysERP\Models\Page;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

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
            'page_url' => $this->faker->url(),
            'fb_page_id' => (string) $this->faker->numerify('##############'),
            'shop_id' => (string) $this->faker->numberBetween(1000, 99999),
            'pos_token' => $this->faker->sha256(),
        ];
    }
}
