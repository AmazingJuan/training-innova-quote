<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The offer is delivered on a business day, so the date asked for is one too.
 */
return new class extends Migration
{
    private const LABEL = 'Fecha estimada de entrega de la oferta comercial';

    private const WITH_NOTICE = 'Seleccione un día hábil. Haremos lo posible por enviársela antes de esa fecha.';

    private const WITHOUT_NOTICE = 'Haremos lo posible por enviársela antes de esa fecha.';

    public function up(): void
    {
        DB::table('conditions')->where('label', self::LABEL)->update(['observation' => self::WITH_NOTICE]);
    }

    public function down(): void
    {
        DB::table('conditions')->where('label', self::LABEL)->update(['observation' => self::WITHOUT_NOTICE]);
    }
};
