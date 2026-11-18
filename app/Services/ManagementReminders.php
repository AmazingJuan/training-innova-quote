<?php

namespace App\Services;

use App\Mail\QuotationReminderAdmin;
use App\Models\QuotationProposalOrder;
use App\Support\BusinessCalendar;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reminds management of every request still waiting for its quotation, every
 * 2.5 business days counted from the moment it was sent, at 8:00 a. m. or
 * 2:00 p. m. of a business day, until the quotation is marked as ready
 * (Acta Meeting Control 2, Ajustes Reto 2, 03-11-2026).
 *
 * A request is due when five more turns have gone by since its last reminder;
 * one that missed a turn (the scheduler was down) gets a single reminder on
 * the next run, not a burst.
 */
class ManagementReminders
{
    /** Two and a half business days, in turns of half a day. */
    public const TURNS_PER_REMINDER = 5;

    public function __construct(
        private readonly Settings $settings,
        private readonly Mailer $mailer,
    ) {}

    /**
     * The requests due for a reminder at `$now`, with the reminder number and
     * the business days they have been waiting.
     *
     * @return list<array{order: QuotationProposalOrder, number: int, days: float}>
     */
    public function due(CarbonInterface $now): array
    {
        if (! BusinessCalendar::isBusinessDay($now)) {
            return [];
        }

        $due = [];

        QuotationProposalOrder::query()
            ->pending()
            ->with(['client', 'businessUnit'])
            ->orderBy('created_at')
            ->each(function (QuotationProposalOrder $order) use ($now, &$due) {
                $turns = BusinessCalendar::turnsBetween($order->getCreatedAt(), $now);
                $number = intdiv($turns, self::TURNS_PER_REMINDER);

                if ($number > $order->getRemindersSent()) {
                    $due[] = ['order' => $order, 'number' => $number, 'days' => $turns / 2];
                }
            });

        return $due;
    }

    /**
     * Send the reminders due at `$now`. A reminder that fails is not marked,
     * so the next run tries again.
     *
     * @return array{sent: int, failed: int, skipped: ?string}
     */
    public function send(CarbonInterface $now): array
    {
        $email = $this->settings->notificationEmail();

        if ($email === null) {
            Log::warning('Recordatorios a gerencia sin enviar: falta el correo de notificaciones.');

            return ['sent' => 0, 'failed' => 0, 'skipped' => 'Falta el correo de notificaciones en Configuración.'];
        }

        $sent = $failed = 0;

        foreach ($this->due($now) as ['order' => $order, 'number' => $number, 'days' => $days]) {
            try {
                $this->mailer->to($email)->send(new QuotationReminderAdmin($order, $number, $days, $order->isOverdue($now)));
            } catch (Throwable $e) {
                Log::error('No se pudo enviar un recordatorio a gerencia.', ['order' => $order->getId(), 'message' => $e->getMessage()]);
                $failed++;

                continue;
            }

            $order->setRemindersSent($number, $now);
            $order->save();
            $sent++;
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => null];
    }
}
