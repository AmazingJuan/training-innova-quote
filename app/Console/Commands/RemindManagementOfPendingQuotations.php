<?php

namespace App\Console\Commands;

use App\Services\ManagementReminders;
use Illuminate\Console\Command;

/**
 * Runs at 8:00 a. m. and 2:00 p. m. on weekdays (bootstrap/app.php); the
 * rules live in ManagementReminders.
 */
class RemindManagementOfPendingQuotations extends Command
{
    protected $signature = 'quotations:remind-management {--dry-run : Lista lo que se enviaría sin enviar nada}';

    protected $description = 'Envía a Gerencia los recordatorios de solicitudes de cotización pendientes (cada 2,5 días hábiles)';

    public function handle(ManagementReminders $reminders): int
    {
        $now = now();

        if ($this->option('dry-run')) {
            foreach ($reminders->due($now) as ['order' => $order, 'number' => $number]) {
                $this->line("Se enviaría el recordatorio {$number} de {$order->getReference()}".($order->isOverdue($now) ? ' (vencida)' : ''));
            }

            return self::SUCCESS;
        }

        $result = $reminders->send($now);

        if ($result['skipped'] !== null) {
            $this->warn($result['skipped']);
        }

        $this->info("Recordatorios enviados: {$result['sent']}.".($result['failed'] > 0 ? " Fallidos: {$result['failed']} (se reintentan en el próximo turno)." : ''));

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
