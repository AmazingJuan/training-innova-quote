<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_signs_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'una-clave-segura']);

        $this->post('/login', ['email' => $user->email, 'password' => 'una-clave-segura'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_a_wrong_password_is_rejected_and_throttled(): void
    {
        $user = User::factory()->create(['password' => 'una-clave-segura']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'otra'])->assertSessionHasErrors('email');
        }

        // Even the right password is refused after five failures in a row.
        $this->post('/login', ['email' => $user->email, 'password' => 'una-clave-segura'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_passwords_are_not_reset_by_email(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'a@b.c'])->assertNotFound();
        $this->get('/reset-password/token')->assertNotFound();
        $this->post('/reset-password', [])->assertNotFound();
    }

    public function test_the_panel_needs_a_session_and_the_removed_auth_pages_are_gone(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get('/verify-email')->assertNotFound();
        $this->get('/confirm-password')->assertNotFound();
    }
}
