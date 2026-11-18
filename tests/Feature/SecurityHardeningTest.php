<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_request_form_is_rate_limited_per_connection(): void
    {
        // Invalid posts are enough: the limit counts every attempt from the connection.
        for ($i = 0; $i < 10; $i++) {
            $this->from('/cotizar')->post('/cotizar', [])->assertSessionDoesntHaveErrors('request');
        }

        $this->from('/cotizar')->post('/cotizar', [])
            ->assertRedirect('/cotizar')
            ->assertSessionHasErrors('request');
    }

    public function test_the_api_no_longer_exists(): void
    {
        $this->postJson('/api/login', ['email' => 'a@b.c', 'password' => 'x'])->assertNotFound();
        $this->getJson('/api/quotations')->assertNotFound();
    }

    public function test_the_initial_admin_comes_from_the_environment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, User::count());

        putenv('ADMIN_EMAIL=gerencia@example.com');
        putenv('ADMIN_PASSWORD=una-clave-larga-segura');
        try {
            $this->seed(DatabaseSeeder::class);
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }

        $admin = User::sole();
        $this->assertSame('gerencia@example.com', $admin->email);
        $this->assertTrue(Hash::check('una-clave-larga-segura', $admin->password));
    }
}
