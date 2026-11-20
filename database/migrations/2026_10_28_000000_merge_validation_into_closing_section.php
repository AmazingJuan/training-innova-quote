<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Validación de requisitos" and "Cierre de la solicitud" are one block of the
 * encuadre now: the questions of the first move to the second.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('conditions')->where('section', 'validacion')->update(['section' => 'cierre']);
    }

    public function down(): void
    {
        // The questions that were in "validacion" can no longer be told apart.
    }
};
