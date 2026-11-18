<?php

namespace Tests\Feature;

use App\Mail\QuotationReminderAdmin;
use App\Models\Client;
use App\Models\Configuration;
use App\Models\QuotationProposalOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ManagementRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Configuration::query()->update(['notification_email' => 'gerencia@example.com']);
    }

    public function test_reminders_follow_the_acta_example_and_stop_when_the_quotation_is_ready(): void
    {
        $this->travelTo('2026-11-23 09:00');
        $order = $this->order('30/11/2026');

        $this->runAt('2026-11-24 14:00');
        Mail::assertNothingSent();

        $this->runAt('2026-11-25 14:00');
        Mail::assertSent(QuotationReminderAdmin::class, fn ($m) => $m->hasTo('gerencia@example.com') && $m->number === 1 && ! $m->overdue);

        // Same turn again: nothing new.
        $this->runAt('2026-11-25 14:00');
        Mail::assertSentCount(1);

        $this->runAt('2026-11-30 08:00');
        Mail::assertSentCount(2);
        $this->assertSame(2, $order->fresh()->getRemindersSent());

        $order->update(['is_generated' => true]);
        $this->runAt('2026-12-07 08:00');
        Mail::assertSentCount(2);
    }

    public function test_a_reminder_after_the_offer_date_says_it_is_overdue(): void
    {
        $this->travelTo('2026-11-23 09:00');
        $this->order('24/11/2026');

        $this->runAt('2026-11-25 14:00');

        Mail::assertSent(QuotationReminderAdmin::class, function ($m) {
            $html = $m->render();

            return $m->overdue && str_contains($m->envelope()->subject, 'vencida') && str_contains($html, 'ya pasó');
        });
    }

    public function test_nothing_is_sent_on_a_holiday(): void
    {
        $this->travelTo('2026-11-10 09:00');
        $this->order('30/11/2026');

        // Monday 16 Nov 2026 is a holiday.
        $this->runAt('2026-11-16 14:00');
        Mail::assertNothingSent();
    }

    private function runAt(string $moment): void
    {
        $this->travelTo($moment);
        $this->artisan('quotations:remind-management')->assertSuccessful();
    }

    private function order(string $offerDate): QuotationProposalOrder
    {
        return QuotationProposalOrder::create([
            'quotation_code' => 'AUD-V-2601',
            'client_id' => Client::create(['name' => 'Ana', 'email' => 'ana@empresa.com', 'company' => 'Clínica Sana'])->id,
            'business_unit' => 'Auditoría',
            'services' => [],
            'answers' => ['Fecha estimada de entrega de la oferta comercial' => $offerDate],
        ]);
    }
}
