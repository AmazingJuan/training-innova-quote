<?php

namespace Tests\Feature;

use App\Mail\QuotationGenerated;
use App\Mail\QuotationGeneratedAdmin;
use App\Mail\WelcomeEmail;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\Configuration;
use App\Models\QuotationProposalOrder;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Mail::fake();
    }

    public function test_requests_are_listed_and_searched_by_code_unit_or_client(): void
    {
        $auditoria = BusinessUnit::factory()->create(['display_name' => 'Auditoría']);
        QuotationProposalOrder::factory()->create(['quotation_code' => 'EO-V-2601', 'business_unit_id' => $auditoria->id]);
        QuotationProposalOrder::factory()->create([
            'quotation_code' => 'GC-V-2601',
            'client_id' => Client::factory()->state(['company' => 'Clínica Sana']),
        ]);

        $codes = fn (string $search) => $this->get(route('dashboard.quotation-orders.index', ['search' => $search]))
            ->assertOk()
            ->viewData('page')['props']['viewData']['quotationOrders']['data'];

        $this->assertSame(['EO-V-2601'], array_column($codes('EO-V'), 'quotation_code'));
        $this->assertSame(['EO-V-2601'], array_column($codes('Auditor'), 'quotation_code'));
        $this->assertSame(['GC-V-2601'], array_column($codes('Clínica'), 'quotation_code'));
        // "%" is searched literally, not as a wildcard.
        $this->assertSame([], $codes('%'));
    }

    public function test_a_request_shows_its_unit_answers_and_offer_date(): void
    {
        $order = QuotationProposalOrder::factory()->create(['offer_date' => '2026-11-25']);

        $this->get(route('dashboard.quotation-orders.show', $order))->assertInertia(fn (Assert $page) => $page
            ->component('admin/quotation-orders/show')
            ->where('viewData.quotationOrder.offer_date', '2026-11-25')
            ->has('viewData.quotationOrder.business_unit.display_name')
            ->has('viewData.quotationOrder.answers'));
    }

    public function test_uploading_the_quotation_marks_it_ready_and_mails_the_client_and_management(): void
    {
        Configuration::query()->firstOrFail()->update(['notification_email' => 'gerencia@example.com']);
        $order = QuotationProposalOrder::factory()->create(['viewed_by_client' => true]);

        $this->post(route('dashboard.quotation-orders.upload-quotation-url', $order), ['quotation_url' => 'https://docs.example.com/cotizacion.pdf'])
            ->assertRedirect(route('dashboard.quotation-orders.show', $order))
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertTrue($order->getIsGenerated());
        $this->assertFalse($order->getViewedByClient());
        $this->assertSame('https://docs.example.com/cotizacion.pdf', $order->getQuotationUrl());
        Mail::assertQueued(QuotationGenerated::class, fn ($mail) => $mail->hasTo($order->getClient()->getEmail()));
        Mail::assertQueued(QuotationGeneratedAdmin::class, fn ($mail) => $mail->hasTo('gerencia@example.com'));

        // A ready request gets no more reminders.
        $this->assertFalse(QuotationProposalOrder::query()->pending()->whereKey($order->getId())->exists());
    }

    public function test_the_quotation_url_must_be_a_url(): void
    {
        $order = QuotationProposalOrder::factory()->create();

        $this->post(route('dashboard.quotation-orders.upload-quotation-url', $order), ['quotation_url' => 'no es una url'])
            ->assertSessionHasErrors('quotation_url');
        $this->assertFalse($order->fresh()->getIsGenerated());
    }

    public function test_saving_the_configuration_refreshes_the_cached_settings(): void
    {
        $settings = app(Settings::class);
        $this->assertNotSame('nuevo@example.com', $settings->notificationEmail());

        $this->put(route('dashboard.configurations.update'), ['notification_email' => 'nuevo@example.com', 'company_website_url' => 'https://trainingcorporation.com.co'])
            ->assertSessionHas('success');

        $this->assertSame('nuevo@example.com', $settings->notificationEmail());
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('company.website_url', 'https://trainingcorporation.com.co'));
    }

    public function test_clients_are_filtered_by_the_unit_they_asked(): void
    {
        $formacion = BusinessUnit::factory()->create(['name' => 'formacion', 'display_name' => 'Formación']);
        QuotationProposalOrder::factory()->create(['business_unit_id' => $formacion->id, 'client_id' => Client::factory()->state(['name' => 'Ana'])]);
        QuotationProposalOrder::factory()->create(['client_id' => Client::factory()->state(['name' => 'Luis'])]);

        $this->get(route('dashboard.clients.index', ['business_unit' => 'formacion']))->assertInertia(fn (Assert $page) => $page
            ->where('viewData.clients.data.0.name', 'Ana')
            ->count('viewData.clients.data', 1)
            ->where('viewData.options.businessUnits', fn ($units) => collect($units)->contains(['name' => 'formacion', 'display_name' => 'Formación'])));

        $client = Client::query()->where('name', 'Ana')->sole();
        $this->get(route('dashboard.clients.show', $client))->assertInertia(fn (Assert $page) => $page
            ->where('viewData.quotations.0.business_unit', 'Formación'));
    }

    public function test_welcome_emails_go_to_the_valid_addresses_of_the_list(): void
    {
        $this->post(route('dashboard.welcome-broadcast.store'), ['emails_text' => "ana@empresa.com\nesto no es un correo\nluis@empresa.com"])
            ->assertSessionHas('success');

        Mail::assertQueued(WelcomeEmail::class, 2);
        Mail::assertQueued(WelcomeEmail::class, fn ($mail) => $mail->hasTo('ana@empresa.com'));
    }
}
