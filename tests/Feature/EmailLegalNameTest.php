<?php

namespace Tests\Feature;

use App\Mail\QuotationGenerated;
use App\Mail\QuotationPending;
use App\Mail\WelcomeEmail;
use App\Models\QuotationProposalOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Tests\TestCase;

/** Emails to clients name the company by its legal name. */
class EmailLegalNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_emails_never_name_the_company_without_its_legal_form(): void
    {
        $order = QuotationProposalOrder::factory()->create();

        foreach ([new WelcomeEmail('Ana'), new QuotationPending($order), new QuotationGenerated($order)] as $mail) {
            /** @var Mailable $mail */
            $html = $mail->render();
            $this->assertStringContainsString('Equipo de Training Corporation S.A.S.', $html, $mail::class);
            $this->assertSame(0, preg_match('/Training Corporation(?! S\.A\.S\.)/', strip_tags($html)), $mail::class);
        }
    }
}
