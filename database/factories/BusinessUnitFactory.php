<?php

namespace Database\Factories;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessUnit> */
class BusinessUnitFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => $name,
            'display_name' => ucfirst($name),
            'abbreviation' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
