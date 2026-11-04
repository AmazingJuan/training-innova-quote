<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Christian Correa',
            'email' => 'profesional-INN@trainingcorporation.com.co',
            'password' => Hash::make('profesionalINN'),
            'phone' => '3107288219',
        ]);

        User::create([
            'name' => 'Eafit',
            'email' => 'reto2@eafit.edu.co',
            'password' => Hash::make('reto2p2*'),
            'phone' => '123456789',
        ]);
    }
}
