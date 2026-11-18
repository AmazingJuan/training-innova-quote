<?php

namespace Tests\Feature;

use App\Jobs\GenerateClientsExport;
use App\Models\BusinessUnit;
use App\Models\QuotationProposalOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_admin_routes_require_authentication(): void
    {
        auth()->logout();

        $this->get(route('dashboard.users.index'))->assertRedirect(route('login'));
    }

    public function test_non_numeric_ids_are_not_found_instead_of_crashing(): void
    {
        $this->get('/dashboard/servicios/abc/editar')->assertNotFound();
        $this->get('/dashboard/lineas-gestion/abc/editar')->assertNotFound();
        $this->get('/dashboard/usuarios/999/editar')->assertNotFound();
    }

    public function test_business_unit_slug_is_derived_and_must_be_unique(): void
    {
        $this->post(route('dashboard.business-unit.store'), ['display_name' => 'Formación Ejecutiva', 'abbreviation' => 'fe 1'])
            ->assertSessionHasNoErrors();

        $unit = BusinessUnit::sole();
        $this->assertSame('formacion_ejecutiva', $unit->getName());
        $this->assertSame('FE1', $unit->getAbbreviation());

        $this->post(route('dashboard.business-unit.store'), ['display_name' => 'Formacion  ejecutiva', 'abbreviation' => 'FE2'])
            ->assertSessionHasErrors('display_name');
    }

    public function test_business_unit_with_requests_cannot_be_deleted(): void
    {
        $unit = BusinessUnit::factory()->create();
        QuotationProposalOrder::factory()->create(['business_unit_id' => $unit->id]);

        $this->delete(route('dashboard.business-unit.delete', $unit))->assertSessionHas('error');
        $this->assertModelExists($unit);

        $empty = BusinessUnit::factory()->create();
        $this->delete(route('dashboard.business-unit.delete', $empty))->assertSessionHas('success');
        $this->assertModelMissing($empty);
    }

    public function test_the_service_catalog_no_longer_exists(): void
    {
        $this->get('/dashboard/servicios')->assertNotFound();
        $this->get('/dashboard/lineas-gestion')->assertNotFound();
    }

    public function test_updating_a_user_without_password_keeps_the_current_one(): void
    {
        $user = User::factory()->create(['password' => 'secret-123']);

        $this->put(route('dashboard.users.update', $user), [
            'name' => 'Nuevo', 'email' => $user->email, 'phone' => '3001234567', 'password' => '',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('secret-123', $user->fresh()->password));
        $this->assertSame('Nuevo', $user->fresh()->name);
    }

    public function test_admin_cannot_delete_itself(): void
    {
        $this->delete(route('dashboard.users.delete', $this->admin))->assertSessionHas('error');
        $this->assertModelExists($this->admin);
    }

    public function test_client_export_download_is_single_use(): void
    {
        Storage::fake('local');
        $token = '9b2f4a1e-1c2d-4e5f-8a9b-0c1d2e3f4a5b';
        Storage::disk('local')->put(GenerateClientsExport::relativePath($token), 'xlsx');
        GenerateClientsExport::markStatus($token, GenerateClientsExport::STATUS_READY);

        $this->get(route('dashboard.clients.export.download', $token))->assertOk()->assertDownload();
        $this->get(route('dashboard.clients.export.download', $token))->assertNotFound();
    }
}
