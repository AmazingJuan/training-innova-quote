<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    public function test_a_forgotten_password_is_reset_through_the_mailed_link(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'clave-nueva-segura',
                'password_confirmation' => 'clave-nueva-segura',
            ])->assertSessionHasNoErrors();

            return true;
        });

        $this->assertTrue(Hash::check('clave-nueva-segura', $user->fresh()->password));
    }

    public function test_the_panel_needs_a_session_and_the_removed_auth_pages_are_gone(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get('/verify-email')->assertNotFound();
        $this->get('/confirm-password')->assertNotFound();
    }
}
