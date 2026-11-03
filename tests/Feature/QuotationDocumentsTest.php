<?php

namespace Tests\Feature;

use App\Jobs\NotifyManagementOfQuotation;
use App\Mail\QuotationPendingAdmin;
use App\Models\BusinessUnit;
use App\Models\Condition;
use App\Models\Configuration;
use App\Models\QuotationProposalOrder;
use App\Support\QuotationAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class QuotationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(QuotationAttachment::DISK);

        Configuration::query()->delete();
        Configuration::create(['notification_email' => 'gerencia@trainingcorporation.com.co']);

        $unit = BusinessUnit::create(['name' => 'consultoria', 'display_name' => 'Consultoría', 'abbreviation' => 'CON']);
        $files = Condition::create(['label' => 'Adjuntar documentos', 'interaction_type' => 'input', 'type' => 'file', 'required' => false, 'business_unit_id' => $unit->id]);
        $first = Condition::create(['label' => 'Sector', 'interaction_type' => 'input', 'type' => 'text', 'business_unit_id' => $unit->id, 'next_condition_id' => $files->id]);
        $unit->update(['initial_condition_id' => $first->id]);
    }

    public function test_the_documents_wait_on_the_attachments_disk_for_the_notice_to_management(): void
    {
        Mail::fake();
        Bus::fake();

        $this->post('/cotizar', $this->payload([
            UploadedFile::fake()->create('Mapa de procesos.pdf', 300, 'application/pdf'),
            UploadedFile::fake()->create('organigrama.xlsx', 50),
        ]))->assertSessionHasNoErrors();

        $order = QuotationProposalOrder::sole();
        $this->assertSame('Mapa de procesos.pdf, organigrama.xlsx', $order->getAnswers()['Adjuntar documentos']);

        Bus::assertDispatched(function (NotifyManagementOfQuotation $job) use ($order) {
            $this->assertSame($order->getId(), $job->orderId);
            $this->assertSame(['Mapa de procesos.pdf', 'organigrama.xlsx'], array_column($job->files, 'name'));

            foreach ($job->files as $file) {
                Storage::disk(QuotationAttachment::DISK)->assertExists($file['path']);
                $this->assertStringStartsWith(QuotationAttachment::DIRECTORY.'/', $file['path']);
            }

            return true;
        });
    }

    public function test_the_notice_carries_the_documents_and_deletes_them_once_sent(): void
    {
        // The real e-mail, through the in-memory transport: what matters is what goes out.
        config(['mail.default' => 'array']);
        $order = QuotationProposalOrder::create([
            'quotation_code' => 'CON-V-2601',
            'business_unit' => 'Consultoría',
            'services' => [],
            'answers' => ['Adjuntar documentos' => 'alcance.pdf'],
        ]);
        $files = QuotationAttachment::keep([['file' => UploadedFile::fake()->createWithContent('alcance.pdf', '%PDF-1.4 alcance')]]);
        Storage::disk(QuotationAttachment::DISK)->assertExists($files[0]['path']);

        (new NotifyManagementOfQuotation($order->getId(), $files))->handle();

        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        $email = $messages[0]->getOriginalMessage();
        $this->assertSame(['gerencia@trainingcorporation.com.co'], array_map(fn ($to) => $to->getAddress(), $email->getTo()));
        $this->assertStringContainsString('CON-V-2601', $email->getSubject());
        $this->assertStringContainsString('alcance.pdf', $email->getHtmlBody());

        // Among the parts of the e-mail (the layout embeds the logo) travels the client's document.
        $attached = array_values(array_filter($email->getAttachments(), fn ($part) => $part->getFilename() === 'alcance.pdf'));
        $this->assertCount(1, $attached, 'el documento del cliente viaja en el correo');
        $this->assertSame('%PDF-1.4 alcance', $attached[0]->getBody());

        Storage::disk(QuotationAttachment::DISK)->assertMissing($files[0]['path']);
    }

    public function test_the_documents_are_deleted_even_when_management_has_no_address(): void
    {
        Mail::fake();
        Configuration::query()->update(['notification_email' => null]);

        $order = QuotationProposalOrder::create([
            'quotation_code' => 'CON-V-2601',
            'business_unit' => 'Consultoría',
            'services' => [],
            'answers' => [],
        ]);
        $files = QuotationAttachment::keep([['file' => UploadedFile::fake()->create('a.pdf', 10)]]);

        (new NotifyManagementOfQuotation($order->getId(), $files))->handle();

        Mail::assertNotSent(QuotationPendingAdmin::class);
        Storage::disk(QuotationAttachment::DISK)->assertMissing($files[0]['path']);
    }

    public function test_a_failed_notice_keeps_the_documents_for_the_retry_and_drops_them_at_the_end(): void
    {
        $order = QuotationProposalOrder::create([
            'quotation_code' => 'CON-V-2602',
            'business_unit' => 'Consultoría',
            'services' => [],
            'answers' => [],
        ]);
        $files = QuotationAttachment::keep([['file' => UploadedFile::fake()->create('a.pdf', 10)]]);
        $job = new NotifyManagementOfQuotation($order->getId(), $files);

        // A mail failure must leave the attachments where the next try will find them.
        Mail::shouldReceive('to->send')->once()->andThrow(new RuntimeException('smtp caído'));
        try {
            $job->handle();
            $this->fail('the mail failure should surface so the job is retried');
        } catch (RuntimeException) {
            Storage::disk(QuotationAttachment::DISK)->assertExists($files[0]['path']);
        }

        $job->failed(new RuntimeException('smtp caído'));

        Storage::disk(QuotationAttachment::DISK)->assertMissing($files[0]['path']);
    }

    public function test_the_sweeper_only_removes_the_attachments_left_behind(): void
    {
        Mail::fake();
        $disk = Storage::disk(QuotationAttachment::DISK);
        $waiting = QuotationAttachment::keep([['file' => UploadedFile::fake()->create('reciente.pdf', 10)]])[0]['path'];
        $stale = QuotationAttachment::DIRECTORY.'/olvidado.pdf';
        $disk->put($stale, 'viejo');
        touch($disk->path($stale), now()->subHours(QuotationAttachment::RETENTION_HOURS + 1)->getTimestamp());

        $this->artisan('adjuntos:clean-cotizaciones')->assertSuccessful();

        $disk->assertMissing($stale);
        $disk->assertExists($waiting);
    }

    public function test_documents_are_optional(): void
    {
        Mail::fake();
        Bus::fake();

        $this->post('/cotizar', $this->payload())->assertSessionHasNoErrors();

        Bus::assertDispatched(fn (NotifyManagementOfQuotation $job) => $job->files === []);
        $this->assertSame([], Storage::disk(QuotationAttachment::DISK)->allFiles());
    }

    public function test_type_size_and_number_of_documents_are_limited(): void
    {
        Mail::fake();
        $this->post('/cotizar', $this->payload([UploadedFile::fake()->create('virus.exe', 10)]))
            ->assertSessionHasErrors('documents.0.file');
        $this->post('/cotizar', $this->payload([UploadedFile::fake()->create('grande.pdf', QuotationAttachment::MAX_KILOBYTES + 1)]))
            ->assertSessionHasErrors('documents.0.file');
        $this->post('/cotizar', $this->payload(array_map(
            fn ($n) => UploadedFile::fake()->create("doc{$n}.pdf", 10),
            range(1, QuotationAttachment::MAX_FILES + 1),
        )))->assertSessionHasErrors('documents');

        $this->assertSame(0, QuotationProposalOrder::count());
        $this->assertSame([], Storage::disk(QuotationAttachment::DISK)->allFiles());
    }

    public function test_a_document_must_belong_to_an_answered_question(): void
    {
        Mail::fake();
        $payload = $this->payload([UploadedFile::fake()->create('a.pdf', 10)]);
        $payload['documents'][0]['question'] = 'Pregunta que no se respondió';

        $this->post('/cotizar', $payload)->assertSessionHasErrors('documents.0.question');

        $this->assertSame(0, QuotationProposalOrder::count());
    }

    public function test_bots_caught_by_the_honeypot_leave_no_documents(): void
    {
        Mail::fake();
        $this->post('/cotizar', [...$this->payload([UploadedFile::fake()->create('a.pdf', 10)]), 'web' => 'spam']);

        $this->assertSame([], Storage::disk(QuotationAttachment::DISK)->allFiles());
        Mail::assertNothingSent();
    }

    /**
     * @param  list<UploadedFile>  $documents
     * @return array<string, mixed>
     */
    private function payload(array $documents = []): array
    {
        return [
            'contact' => ['nit' => '900123456-7', 'economic_activity' => 'Servicios de salud', 'address' => 'Cra. 50 # 10-20, Medellín', 'name' => 'Ana', 'email' => 'ana@empresa.com', 'phone' => '3001234567', 'company' => 'Clínica Sana', 'role' => 'Coordinadora HSEQ'],
            'businessUnit' => 'Consultoría',
            'answers' => ['Sector' => 'Salud', ...($documents ? ['Adjuntar documentos' => implode(', ', array_map(fn ($f) => $f->getClientOriginalName(), $documents))] : [])],
            'documents' => array_map(fn ($file) => ['question' => 'Adjuntar documentos', 'file' => $file], $documents),
            'privacy_accepted' => true,
        ];
    }
}
