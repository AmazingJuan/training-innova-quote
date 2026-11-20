<?php

namespace Tests\Feature;

use App\Enums\ExportStatus;
use App\Jobs\GenerateClientsExport;
use App\Models\BusinessUnit;
use App\Models\QuotationProposalOrder;
use App\Models\User;
use App\Support\ClientExportStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
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
            'name' => 'Nuevo', 'email' => $user->email, 'phone' => '3001234567', 'password' => '', 'role' => 'user',
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
        $exports = app(ClientExportStore::class);
        $token = $exports->open();
        $exports->disk()->put($exports->path($token), 'xlsx');
        $exports->mark($token, ExportStatus::Ready);

        $this->getJson(route('dashboard.clients.export.status', $token))->assertJson(['status' => 'ready']);
        $this->get(route('dashboard.clients.export.download', $token))->assertOk()->assertDownload();
        $this->get(route('dashboard.clients.export.download', $token))->assertNotFound();
    }

    public function test_the_client_export_runs_in_the_background_and_is_swept_if_never_downloaded(): void
    {
        Storage::fake('local');
        Queue::fake();

        $token = $this->postJson(route('dashboard.clients.export.start'))->assertOk()->json('token');
        $this->getJson(route('dashboard.clients.export.status', $token))->assertJson(['status' => 'pending']);
        Queue::assertPushed(GenerateClientsExport::class, fn (GenerateClientsExport $job) => $job->token === $token);

        $exports = app(ClientExportStore::class);
        $exports->disk()->put($exports->path($token), 'xlsx');
        $this->travel(ClientExportStore::RETENTION_HOURS + 1)->hours();
        $this->artisan('clients:clean-exports')->assertSuccessful();

        $exports->disk()->assertMissing($exports->path($token));
    }
}
