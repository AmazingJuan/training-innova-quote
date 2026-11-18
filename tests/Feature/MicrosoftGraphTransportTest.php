<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class MicrosoftGraphTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'msgraph',
            'mail.from.address' => 'gerencia@example.com',
            'cache.default' => 'array',
            'services.msgraph' => ['tenant_id' => 'tenant', 'client_id' => 'client', 'client_secret' => 'secret', 'from' => 'gerencia@example.com'],
        ]);
        Cache::flush();
    }

    public function test_a_small_message_goes_in_one_sendmail_call_as_mime(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(null, 202),
        ]);

        Mail::raw('Hola', fn ($m) => $m->to('cliente@example.com')->subject('Prueba'));
        Mail::raw('Otra', fn ($m) => $m->to('cliente@example.com')->subject('Prueba 2'));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://graph.microsoft.com/v1.0/users/gerencia%40example.com/sendMail'
            && $r->hasHeader('Authorization', 'Bearer tok')
            && str_contains(base64_decode($r->body()), 'Subject: Prueba'));
        // The token is reused.
        Http::assertSentCount(3);
    }

    public function test_a_big_message_is_sent_as_a_draft_with_an_upload_session(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'graph.microsoft.com/v1.0/users/*/messages/draft-1/attachments/createUploadSession' => Http::response(['uploadUrl' => 'https://outlook.office.com/upload/xyz']),
            'graph.microsoft.com/v1.0/users/*/messages/draft-1/send' => Http::response(null, 202),
            'graph.microsoft.com/v1.0/users/*/messages' => Http::response(['id' => 'draft-1'], 201),
            'outlook.office.com/*' => Http::response(null, 200),
        ]);

        Mail::to('gerencia@example.com')->send(new class extends Mailable
        {
            public function build(): static
            {
                return $this->subject('Con adjunto')->html('<p>Documentos</p>')
                    ->attach(Attachment::fromData(fn () => str_repeat('a', 5_000_000), 'grande.pdf')->withMime('application/pdf'));
            }
        });

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/messages') && $r['subject'] === 'Con adjunto');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/createUploadSession') && $r['AttachmentItem']['size'] === 5_000_000);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://outlook.office.com/upload/xyz' && $r->header('Content-Range')[0] === 'bytes 0-3276799/5000000');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://outlook.office.com/upload/xyz' && $r->header('Content-Range')[0] === 'bytes 3276800-4999999/5000000');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/messages/draft-1/send'));
    }

    public function test_a_403_explains_the_missing_permission(): void
    {
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'tok', 'expires_in' => 3600]),
            'graph.microsoft.com/*' => Http::response(['error' => ['message' => 'Access is denied.']], 403),
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/permiso denegado.*Mail\.Send/');

        Mail::raw('Hola', fn ($m) => $m->to('cliente@example.com')->subject('Prueba'));
    }
}
