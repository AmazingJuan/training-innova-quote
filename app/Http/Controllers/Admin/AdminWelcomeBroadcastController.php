<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminWelcomeBroadcastRequest;
use App\Services\MailService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminWelcomeBroadcastController extends Controller
{
    private const MAX_RECIPIENTS = 500;

    public function __construct(private readonly MailService $mail) {}

    public function create(): InertiaResponse
    {
        return Inertia::render('admin/welcome-broadcast/create', [
            'viewData' => [
                'app_url' => route('quotation.select.business_unit'),
            ],
        ]);
    }

    public function store(AdminWelcomeBroadcastRequest $request): RedirectResponse
    {
        $parsed = $this->parseEmails($request->string('emails_text')->toString());

        if ($parsed['valid'] === []) {
            return back()->withErrors([
                'emails_text' => 'No se encontró ningún correo válido. Verifique el formato (ejemplo: nombre@empresa.com).',
            ]);
        }

        if (count($parsed['valid']) > self::MAX_RECIPIENTS) {
            return back()->withErrors([
                'emails_text' => 'Máximo '.self::MAX_RECIPIENTS.' correos por envío. Reduzca la lista e intente nuevamente.',
            ]);
        }

        foreach ($parsed['valid'] as $email) {
            $this->mail->sendWelcome($email);
        }

        $sent = count($parsed['valid']);
        $message = $sent === 1
            ? 'Se puso en cola 1 correo de bienvenida.'
            : "Se pusieron en cola {$sent} correos de bienvenida.";

        if ($parsed['invalid_count'] > 0) {
            $sample = implode(', ', array_slice($parsed['invalid_samples'], 0, 5));
            $more = $parsed['invalid_count'] > 5 ? '…' : '';
            $message .= ' Se omitieron '.$parsed['invalid_count'].' líneas sin correo válido (ej.: '.$sample.$more.').';
        }

        return to_route('dashboard.welcome-broadcast.create')->with('success', $message);
    }

    /**
     * @return array{valid: list<string>, invalid_count: int, invalid_samples: list<string>}
     */
    private function parseEmails(string $raw): array
    {
        $parts = preg_split('/[\r\n,;|]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = [];
        $invalidSamples = [];
        $invalidCount = 0;

        foreach ($parts as $part) {
            $email = strtolower(trim($part));
            $email = trim($email, " \t\n\r\0\x0B\"'<>");
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[$email] = true;
            } else {
                $invalidCount++;
                if (count($invalidSamples) < 10) {
                    $invalidSamples[] = mb_substr(trim($part), 0, 80);
                }
            }
        }

        return [
            'valid' => array_keys($valid),
            'invalid_count' => $invalidCount,
            'invalid_samples' => $invalidSamples,
        ];
    }
}
