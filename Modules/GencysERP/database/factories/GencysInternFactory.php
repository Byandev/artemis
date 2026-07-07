<?php

namespace Modules\GencysERP\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\GencysERP\Models\GencysIntern;

/**
 * @extends Factory<GencysIntern>
 */
class GencysInternFactory extends Factory
{
    protected $model = GencysIntern::class;

    public function definition(): array
    {
        return [
            'full_name' => $this->faker->name(),
            'company_name' => $this->faker->company(),
            'username' => $this->faker->unique()->userName(),
            'contact_number_email' => $this->faker->boolean()
                ? $this->faker->safeEmail()
                : $this->faker->numerify('09#########'),
        ];
    }
}
