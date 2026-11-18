<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The offer date cannot be sooner than 3 business days after the request is
 * sent (Acta Meeting Control 2, Ajustes Reto 2): the field says so.
 */
return new class extends Migration
{
    private const LABEL = 'Fecha estimada de entrega de la oferta comercial';

    private const BEFORE = 'Seleccione un día hábil. Haremos lo posible por enviársela antes de esa fecha.';

    private const AFTER = 'Seleccione un día hábil, como mínimo 3 días hábiles después del envío de la solicitud. Haremos lo posible por enviársela antes de esa fecha.';

    public function up(): void
    {
        DB::table('conditions')->where('label', self::LABEL)->where('observation', self::BEFORE)->update(['observation' => self::AFTER]);
    }

    public function down(): void
    {
        DB::table('conditions')->where('label', self::LABEL)->where('observation', self::AFTER)->update(['observation' => self::BEFORE]);
    }
};
