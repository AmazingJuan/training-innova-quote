<?php

namespace App\Console\Commands;

use App\Mail\QuotationReminderAdmin;
use App\Models\Configuration;
use App\Models\QuotationProposalOrder;
use App\Support\BusinessCalendar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Reminds management of every request still waiting for its quotation, every
 * 2.5 business days counted from the moment it was sent, at 8:00 a. m. or
 * 2:00 p. m. of a business day, until the quotation is marked as ready
 * (Acta Meeting Control 2, Ajustes Reto 2, 03-11-2026).
 *
 * Runs at both turns (see bootstrap/app.php). A request is due when five more
 * turns have gone by since its last reminder; one that missed a turn (the
 * scheduler was down) gets a single reminder on the next run, not a burst.
 */
class RemindManagementOfPendingQuotations extends Command
{
    protected $signature = 'quotations:remind-management {--dry-run : Lista lo que se enviaría sin enviar nada}';

    protected $description = 'Envía a Gerencia los recordatorios de solicitudes de cotización pendientes (cada 2,5 días hábiles)';

    /** Two and a half business days, in turns of half a day. */
    public const TURNS_PER_REMINDER = 5;

    public function handle(): int
    {
        $now = now();

        if (! BusinessCalendar::isBusinessDay($now)) {
            $this->info('Hoy no es día hábil: no se envían recordatorios.');

            return self::SUCCESS;
        }

        $email = Configuration::current()?->getNotificationEmail();
        if ($email === null) {
            $this->warn('Falta el correo de notificaciones en Configuración: no se envían recordatorios.');
            Log::warning('Recordatorios a gerencia sin enviar: falta el correo de notificaciones.');

            return self::SUCCESS;
        }

        $sent = 0;

        QuotationProposalOrder::query()
            ->with('client')
            ->where('is_generated', false)
            ->orderBy('created_at')
            ->each(function (QuotationProposalOrder $order) use ($now, $email, &$sent) {
                $turns = BusinessCalendar::turnsBetween($order->getCreatedAt(), $now);
                $due = intdiv($turns, self::TURNS_PER_REMINDER);

                if ($due <= $order->getRemindersSent()) {
                    return;
                }

                $number = $due;
                $overdue = $order->isOverdue($now);
                $label = ($order->getQuotationCode() ?? $order->getId()).($overdue ? ' (vencida)' : '');

                if ($this->option('dry-run')) {
                    $this->line("Se enviaría el recordatorio {$number} de {$label}");

                    return;
                }

                try {
                    Mail::to($email)->send(new QuotationReminderAdmin($order, $number, $turns / 2, $overdue));
                } catch (Throwable $e) {
                    // Not marked: the next run tries again.
                    Log::error('No se pudo enviar un recordatorio a gerencia.', ['order' => $order->getId(), 'message' => $e->getMessage()]);
                    $this->error("No se pudo enviar el recordatorio de {$label}: {$e->getMessage()}");

                    return;
                }

                $order->markReminded($due);
                $sent++;
                $this->line("Recordatorio {$number} enviado: {$label}");
            });

        $this->info("Recordatorios enviados: {$sent}.");

        return self::SUCCESS;
    }
}
