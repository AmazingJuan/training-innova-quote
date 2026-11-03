<?php

namespace Tests\Feature;

use App\Mail\QuotationPending;
use App\Mail\QuotationPendingAdmin;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\Condition;
use App\Models\Configuration;
use App\Models\GestionLine;
use App\Models\QuotationProposalOrder;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class QuotationFlowTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = BusinessUnit::create(['name' => 'consultoria', 'display_name' => 'Consultoría', 'abbreviation' => 'CON']);
        $line = GestionLine::create(['name' => 'Calidad']);
        Service::create(['name' => 'ISO 9001', 'business_unit_id' => $this->unit->id, 'gestion_line_id' => $line->id]);
        $assets = GestionLine::create(['name' => 'Activos']);
        Service::create(['name' => 'ISO 55001', 'business_unit_id' => $this->unit->id, 'gestion_line_id' => $assets->id]);

        $second = Condition::create(['label' => 'Empleados', 'interaction_type' => 'input', 'type' => 'number', 'business_unit_id' => $this->unit->id]);
        $first = Condition::create(['label' => 'Sector', 'interaction_type' => 'input', 'type' => 'text', 'business_unit_id' => $this->unit->id, 'next_condition_id' => $second->id]);
        Condition::create(['label' => 'Huérfana', 'interaction_type' => 'input', 'type' => 'text', 'business_unit_id' => $this->unit->id]);
        $this->unit->update(['initial_condition_id' => $first->id]);
    }

    public function test_quote_page_exposes_only_reachable_conditions(): void
    {
        $this->get('/cotizar/consultoria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('quotation/index')
                ->where('viewData.businessUnit', 'Consultoría')
                ->missing('viewData.services')
                ->has('viewData.conditions', 2));
    }

    public function test_units_are_offered_when_they_have_a_questionnaire_even_without_services(): void
    {
        $noServices = BusinessUnit::create(['name' => 'formacion', 'display_name' => 'Formación', 'abbreviation' => 'FOR']);
        $noServices->update(['initial_condition_id' => Condition::create(['label' => 'Tema', 'interaction_type' => 'input', 'type' => 'text', 'business_unit_id' => $noServices->id])->id]);
        BusinessUnit::create(['name' => 'vacia', 'display_name' => 'Vacía', 'abbreviation' => 'VAC']);

        $this->get(route('quotation.select.business_unit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewData.businessUnits', [
                    ['name' => 'consultoria', 'display_name' => 'Consultoría'],
                    ['name' => 'formacion', 'display_name' => 'Formación'],
                ]));

        $this->get('/cotizar/formacion')->assertOk();
        $this->get('/cotizar/vacia')->assertRedirect(route('quotation.select.business_unit'));
    }

    public function test_the_professional_choice_no_longer_exists(): void
    {
        $this->get('/cotizar/consultoria')->assertInertia(fn (Assert $page) => $page->missing('viewData.professionals'));

        $this->actingAs(User::factory()->create());
        $this->get('/dashboard/profesionales')->assertNotFound();

        Mail::fake();
        $this->post('/cotizar', $this->payload(['professional_id' => 1]))->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('professional_id', QuotationProposalOrder::sole()->getAttributes());
    }

    public function test_unknown_business_unit_redirects_with_error(): void
    {
        $this->get('/cotizar/no-existe')
            ->assertRedirect(route('quotation.select.business_unit'))
            ->assertSessionHas('error');
    }

    public function test_storing_a_quotation_creates_client_order_and_queues_mails(): void
    {
        Mail::fake();
        Configuration::query()->update(['notification_email' => 'admin@example.com']);

        $this->from('/cotizar/consultoria')->post('/cotizar', $this->payload())
            ->assertRedirect('/cotizar/consultoria')
            ->assertSessionHas('quotation_sent', ['code' => 'CON-V-'.now()->format('y').'01', 'email' => 'ana@empresa.com']);

        $order = QuotationProposalOrder::sole();
        $this->assertSame('CON-V-'.now()->format('y').'01', $order->getQuotationCode());
        $this->assertSame(['Sector' => 'Salud', 'Empleados' => '40'], $order->getAnswers());
        $this->assertSame([], $order->getServices());
        $this->assertNull($order->getGestionLine());
        $this->assertSame('ana@empresa.com', $order->getClient()->getEmail());
        $this->assertSame('3001234567', $order->getClient()->getPhone());

        Mail::assertQueued(QuotationPending::class, fn ($mail) => $mail->hasTo('ana@empresa.com'));
        // Management's notice is sent by its own job, which carries the attachments.
        Mail::assertSent(QuotationPendingAdmin::class, fn ($mail) => $mail->hasTo('admin@example.com'));
    }

    public function test_privacy_authorization_is_required(): void
    {
        $this->post('/cotizar', $this->payload(['privacy_accepted' => false]))->assertSessionHasErrors('privacy_accepted');

        $this->assertSame(0, QuotationProposalOrder::count());
    }

    public function test_repeated_client_is_updated_instead_of_duplicated(): void
    {
        Mail::fake();

        $this->post('/cotizar', $this->payload());
        $this->post('/cotizar', $this->payload(['contact' => ['name' => 'Ana María', 'email' => 'ANA@empresa.com', 'phone' => '300 123 4567', 'company' => 'Clínica Sana', 'role' => 'Coordinadora HSEQ']]));

        $this->assertSame(1, Client::count());
        $this->assertSame('Ana María', Client::sole()->getName());
        $this->assertSame(2, QuotationProposalOrder::count());
    }

    public function test_company_name_is_required(): void
    {
        $this->post('/cotizar', $this->payload(['contact' => ['name' => 'Ana', 'email' => 'ana@empresa.com', 'phone' => '3001234567', 'company' => '']]))
            ->assertSessionHasErrors('contact.company');

        $this->assertSame(0, QuotationProposalOrder::count());
    }

    public function test_contact_role_is_required(): void
    {
        $this->post('/cotizar', $this->payload(['contact' => ['name' => 'Ana', 'email' => 'ana@empresa.com', 'phone' => '3001234567', 'company' => 'Clínica Sana', 'role' => '']]))
            ->assertSessionHasErrors('contact.role');

        $this->assertSame(0, QuotationProposalOrder::count());
    }

    public function test_client_data_of_the_encuadre_is_stored(): void
    {
        Mail::fake();

        $this->post('/cotizar', $this->payload([
            'contact' => [
                'name' => 'Ana', 'email' => 'ana@empresa.com', 'phone' => '3001234567', 'company' => 'Clínica Sana', 'role' => 'Coordinadora HSEQ',
                'nit' => '900123456-7', 'economic_activity' => 'Salud', 'address' => 'Calle 10 # 20-30, Medellín',
            ],
        ]))->assertSessionHasNoErrors();

        $client = QuotationProposalOrder::sole()->getClient();
        $this->assertSame('Clínica Sana', $client->getCompany());
        $this->assertSame('900123456-7', $client->getNit());
        $this->assertSame('Salud', $client->getEconomicActivity());
        $this->assertSame('Calle 10 # 20-30, Medellín', $client->getAddress());
        $this->assertSame('Coordinadora HSEQ', $client->getRole());
    }

    public function test_the_honeypot_answers_like_a_real_request_but_stores_nothing(): void
    {
        Mail::fake();

        $this->from('/cotizar/consultoria')->post('/cotizar', $this->payload(['web' => 'https://spam.example']))
            ->assertRedirect('/cotizar/consultoria')
            ->assertSessionHas('quotation_sent', ['code' => null, 'email' => 'ana@empresa.com']);

        $this->assertSame(0, QuotationProposalOrder::count());
        $this->assertSame(0, Client::count());
        Mail::assertNothingQueued();
    }

    public function test_quote_page_lists_the_units_for_the_tabs(): void
    {
        $this->get('/cotizar/consultoria')->assertInertia(fn (Assert $page) => $page
            ->where('viewData.unit', 'consultoria')
            ->where('viewData.units', [['name' => 'consultoria', 'display_name' => 'Consultoría']]));
    }

    public function test_receive_link_requires_a_valid_signature(): void
    {
        $order = $this->generatedOrder();

        $this->get(route('quotation.receive', $order->getId()))
            ->assertRedirect(route('home'))
            ->assertSessionHas('error');

        $this->assertFalse($order->fresh()->getViewedByClient());
    }

    public function test_receive_link_marks_quotation_as_viewed_and_redirects_to_document(): void
    {
        $order = $this->generatedOrder();

        $this->get(URL::signedRoute('quotation.receive', ['quotationId' => $order->getId()]))
            ->assertRedirect('https://docs.example.com/propuesta.pdf');

        $this->assertTrue($order->fresh()->getViewedByClient());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'contact' => ['name' => 'Ana', 'email' => 'ana@empresa.com', 'phone' => '300 123 4567', 'company' => 'Clínica Sana', 'role' => 'Coordinadora HSEQ'],
            'businessUnit' => 'Consultoría',
            'answers' => ['Sector' => 'Salud', 'Empleados' => '40'],
            'privacy_accepted' => true,
        ], $overrides);
    }

    private function generatedOrder(): QuotationProposalOrder
    {
        return QuotationProposalOrder::create([
            'business_unit' => 'Consultoría',
            'gestion_line' => 'Calidad',
            'services' => ['ISO 9001'],
            'answers' => [],
            'is_generated' => true,
            'quotation_url' => 'https://docs.example.com/propuesta.pdf',
        ]);
    }
}
