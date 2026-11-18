<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company' => fake()->company(),
            'phone' => '3001234567',
            'role' => 'Coordinadora HSEQ',
            'nit' => '900123456-7',
            'economic_activity' => 'Servicios',
            'address' => 'Cra. 50 # 10-20',
        ];
    }
}
