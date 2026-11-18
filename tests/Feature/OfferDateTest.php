<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Condition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfferDateTest extends TestCase
{
    use RefreshDatabase;

    private const LABEL = 'Fecha estimada de entrega de la oferta comercial';

    protected function setUp(): void
    {
        parent::setUp();

        $unit = BusinessUnit::create(['name' => 'auditoria', 'display_name' => 'Auditoría', 'abbreviation' => 'AUD']);
        $date = Condition::create(['label' => self::LABEL, 'interaction_type' => 'input', 'type' => 'date', 'business_unit_id' => $unit->id]);
        $unit->update(['initial_condition_id' => $date->id]);

        // Friday 13 Nov 2026: Monday 16 is a holiday, so the 3 business days are Tue 17, Wed 18 and Thu 19.
        $this->travelTo('2026-11-13 10:00');
    }

    public function test_the_form_gets_the_soonest_offer_date(): void
    {
        $this->get('/cotizar/auditoria')->assertInertia(fn (Assert $page) => $page
            ->where('viewData.conditions.'.Condition::sole()->id.'.min_date', '2026-11-19'));
    }

    public function test_an_offer_date_sooner_than_three_business_days_is_rejected(): void
    {
        Mail::fake();

        $this->post('/cotizar', $this->payload('18/11/2026'))->assertSessionHasErrors('answers');
        $this->post('/cotizar', $this->payload('19/11/2026'))->assertSessionHasNoErrors();
    }

    private function payload(string $date): array
    {
        return [
            'contact' => ['nit' => '900123456-7', 'economic_activity' => 'Salud', 'address' => 'Cra. 50 # 10-20', 'name' => 'Ana', 'email' => 'ana@empresa.com', 'phone' => '3001234567', 'company' => 'Clínica Sana', 'role' => 'Coordinadora'],
            'businessUnit' => 'Auditoría',
            'answers' => [self::LABEL => $date],
            'privacy_accepted' => true,
        ];
    }
}
