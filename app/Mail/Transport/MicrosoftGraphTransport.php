<?php

namespace App\Mail\Transport;

use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Sends the app's e-mail through Microsoft Graph as the management mailbox
 * (gerencia@), with an Entra app that holds "Application Mail.Send" limited to
 * that mailbox by an Exchange RBAC scope. No SMTP password is involved.
 *
 * A message whose MIME fits in one Graph request (4 MB) goes in a single
 * sendMail call. A bigger one (the client's documents) is built as a draft,
 * its attachments are uploaded in chunks and the draft is sent: that path
 * also needs "Application Mail.ReadWrite" on the same scope.
 */
class MicrosoftGraphTransport extends AbstractTransport
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** Graph rejects requests over 4 MB; this leaves room for the request itself. */
    private const MAX_SINGLE_REQUEST_BYTES = 3_800_000;

    /** Attachments up to this size go in one request; bigger ones use an upload session. */
    private const MAX_INLINE_ATTACHMENT_BYTES = 2_900_000;

    /** Upload session chunk: a multiple of 320 KiB, under 4 MB. */
    private const CHUNK_BYTES = 320 * 1024 * 10;

    public function __construct(
        private readonly Http $http,
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $mailbox,
    ) {
        parent::__construct();

        foreach (['tenant_id' => $tenantId, 'client_id' => $clientId, 'client_secret' => $clientSecret, 'from' => $mailbox] as $key => $value) {
            if (trim($value) === '') {
                throw new RuntimeException("Falta services.msgraph.{$key} (MSGRAPH_* en .env) para enviar correo por Microsoft Graph.");
            }
        }
    }

    public function __toString(): string
    {
        return 'msgraph://'.$this->mailbox;
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();
        if (! $original instanceof Message) {
            throw new TransportException('Microsoft Graph solo envía mensajes con encabezados (Symfony\\Component\\Mime\\Message).');
        }
        $email = MessageConverter::toEmail($original);
        $mime = base64_encode($message->toString());

        if (strlen($mime) <= self::MAX_SINGLE_REQUEST_BYTES && $email->getBcc() === []) {
            $this->check($this->graph()->withBody($mime, 'text/plain')->post($this->mailboxUrl('/sendMail')), 'sendMail');

            return;
        }

        $this->sendAsDraft($email);
    }

    /** For big messages (or with Bcc, which MIME sending drops): draft, attachments, send. */
    private function sendAsDraft(Email $email): void
    {
        $draft = $this->check($this->graph()->post($this->mailboxUrl('/messages'), [
            'subject' => (string) $email->getSubject(),
            'body' => $email->getHtmlBody() !== null
                ? ['contentType' => 'HTML', 'content' => (string) $email->getHtmlBody()]
                : ['contentType' => 'Text', 'content' => (string) $email->getTextBody()],
            'toRecipients' => $this->recipients($email->getTo()),
            'ccRecipients' => $this->recipients($email->getCc()),
            'bccRecipients' => $this->recipients($email->getBcc()),
            'replyTo' => $this->recipients($email->getReplyTo()),
        ]), 'crear el borrador')->json('id');

        try {
            foreach ($email->getAttachments() as $attachment) {
                $this->attach($draft, $attachment);
            }

            $this->check($this->graph()->withBody('', 'application/json')->post($this->mailboxUrl('/messages/'.rawurlencode($draft).'/send')), 'enviar el borrador');
        } catch (TransportException $e) {
            // Do not leave a half-built draft in the mailbox.
            $this->graph()->delete($this->mailboxUrl('/messages/'.rawurlencode($draft)));

            throw $e;
        }
    }

    private function attach(string $draftId, DataPart $part): void
    {
        $body = $part->getBody();
        $size = strlen($body);
        $name = $part->getFilename() ?? 'adjunto';
        $isInline = $part->getDisposition() === 'inline';
        $contentId = $isInline ? $part->getContentId() : null;
        $base = $this->mailboxUrl('/messages/'.rawurlencode($draftId).'/attachments');

        if ($size <= self::MAX_INLINE_ATTACHMENT_BYTES) {
            $this->check($this->graph()->post($base, array_filter([
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $name,
                'contentType' => $part->getMediaType().'/'.$part->getMediaSubtype(),
                'contentBytes' => base64_encode($body),
                'isInline' => $isInline,
                'contentId' => $contentId,
            ], fn ($v) => $v !== null)), 'adjuntar '.$name);

            return;
        }

        $uploadUrl = $this->check($this->graph()->post($base.'/createUploadSession', [
            'AttachmentItem' => array_filter([
                'attachmentType' => 'file',
                'name' => $name,
                'size' => $size,
                'isInline' => $isInline,
                'contentId' => $contentId,
            ], fn ($v) => $v !== null),
        ]), 'preparar la carga de '.$name)->json('uploadUrl');

        for ($offset = 0; $offset < $size; $offset += self::CHUNK_BYTES) {
            $chunk = substr($body, $offset, self::CHUNK_BYTES);
            $end = $offset + strlen($chunk) - 1;

            // The upload URL is pre-authenticated: no bearer token.
            $this->check($this->http->timeout(120)
                ->withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$size}"])
                ->withBody($chunk, 'application/octet-stream')
                ->put($uploadUrl), 'cargar '.$name);
        }
    }

    /**
     * @param  list<Address>  $addresses
     * @return list<array{emailAddress: array{address: string, name?: string}}>
     */
    private function recipients(array $addresses): array
    {
        return array_map(fn (Address $a) => ['emailAddress' => array_filter([
            'address' => $a->getAddress(),
            'name' => $a->getName() ?: null,
        ])], $addresses);
    }

    private function graph(): PendingRequest
    {
        return $this->http->withToken($this->token())->acceptJson()->timeout(60);
    }

    private function mailboxUrl(string $path): string
    {
        return self::GRAPH.'/users/'.rawurlencode($this->mailbox).$path;
    }

    /** App-only token (client credentials), cached until shortly before it expires. */
    private function token(): string
    {
        $key = 'msgraph-token:'.sha1($this->tenantId.'|'.$this->clientId.'|'.$this->clientSecret);

        if (is_string($cached = Cache::get($key))) {
            return $cached;
        }

        $response = $this->http->asForm()->timeout(30)->post("https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token", [
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
        ]);

        if (! $response->successful()) {
            throw new TransportException('Microsoft Graph no entregó el token: '.($response->json('error_description') ?? $response->body()));
        }

        $token = (string) $response->json('access_token');
        Cache::put($key, $token, max(60, (int) $response->json('expires_in', 3600) - 300));

        return $token;
    }

    private function check(Response $response, string $action): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $error = $response->json('error.message') ?? $response->body();
        $hint = $response->status() === 403
            ? ' (permiso denegado: revise que la app tenga Mail.Send —y Mail.ReadWrite para mensajes de más de 4 MB— sobre el buzón '.$this->mailbox.'; un permiso recién asignado puede tardar hasta 2 horas)'
            : '';

        throw new TransportException("Microsoft Graph rechazó {$action}: HTTP {$response->status()} {$error}{$hint}");
    }
}
