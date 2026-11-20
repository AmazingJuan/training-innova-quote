<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_regular_user_runs_the_panel_but_cannot_reach_the_accounts(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('auth.can.manageUsers', false));
        $this->get(route('dashboard.quotation-orders.index'))->assertOk();
        $this->get(route('dashboard.configurations.edit'))->assertOk();

        $this->get(route('dashboard.users.index'))->assertForbidden();
        $this->get(route('dashboard.users.create'))->assertForbidden();
        $this->get(route('dashboard.users.edit', $other))->assertForbidden();
        $this->post(route('dashboard.users.store'), $this->payload())->assertForbidden();
        $this->put(route('dashboard.users.update', $other), $this->payload(['email' => $other->email, 'password' => 'otra-clave-123']))->assertForbidden();
        $this->delete(route('dashboard.users.delete', $other))->assertForbidden();

        $this->assertTrue(Hash::check('password', $other->fresh()->password));
        $this->assertSame(2, User::count());
    }

    public function test_an_administrator_manages_accounts_passwords_and_roles(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $other = User::factory()->create();

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('auth.can.manageUsers', true));
        $this->get(route('dashboard.users.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('viewData.users.data.1.role', 'user'));
        $this->get(route('dashboard.users.create'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('viewData.roles', 2));

        $this->put(route('dashboard.users.update', $other), $this->payload(['email' => $other->email, 'password' => 'otra-clave-123', 'role' => 'admin']))
            ->assertRedirect(route('dashboard.users.index'));
        $other->refresh();
        $this->assertTrue(Hash::check('otra-clave-123', $other->password));
        $this->assertSame(UserRole::Admin, $other->role);

        $this->post(route('dashboard.users.store'), $this->payload(['email' => 'nuevo@example.com']))->assertRedirect(route('dashboard.users.index'));
        $this->assertSame(UserRole::User, User::where('email', 'nuevo@example.com')->sole()->role);

        $this->post(route('dashboard.users.store'), $this->payload(['email' => 'raro@example.com', 'role' => 'root']))->assertSessionHasErrors('role');
    }

    public function test_an_administrator_cannot_take_away_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->put(route('dashboard.users.update', $admin), $this->payload(['email' => $admin->email, 'role' => 'user']))
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_the_migration_makes_management_the_administrator(): void
    {
        $management = User::factory()->create(['email' => 'gerencia@trainingcorporation.com.co']);
        $other = User::factory()->create();

        $migration = require database_path('migrations/2026_11_20_000000_add_roles_to_users.php');
        $migration->down();
        $migration->up();

        $this->assertTrue($management->fresh()->isAdmin());
        $this->assertFalse($other->fresh()->isAdmin());
    }

    public function test_the_terminal_creates_administrators(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Nombre', 'Gerencia')
            ->expectsQuestion('Correo electrónico', 'gerencia@example.com')
            ->expectsQuestion('Teléfono', '3001234567')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'una-clave-larga-segura')
            ->assertSuccessful();

        $this->assertTrue(User::sole()->isAdmin());
    }

    public function test_the_welcome_email_links_the_platform_by_name(): void
    {
        (new WelcomeEmail('Ana'))->assertSeeInHtml('Training InnovaQuote')->assertSeeInHtml(route('home'));
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'phone' => '3001234567',
            'password' => 'clave-segura-1',
            'role' => 'user',
            ...$overrides,
        ];
    }
}
