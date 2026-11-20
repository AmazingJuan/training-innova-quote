<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The ARL question asks for the company's risk class (what clients answer),
 * not for the name of the ARL its example suggested.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('conditions')->where('label', 'ARL de la empresa')->update([
            'label' => 'Nivel de riesgo de la empresa ante la ARL',
            'placeholder' => 'Ej.: Riesgo III',
            'observation' => 'Clase de riesgo (I a V) en la que su ARL clasifica a la empresa. Es importante sobre todo si el servicio es de seguridad y salud en el trabajo.',
        ]);
    }

    public function down(): void
    {
        DB::table('conditions')->where('label', 'Nivel de riesgo de la empresa ante la ARL')->update([
            'label' => 'ARL de la empresa',
            'placeholder' => 'Ej.: Positiva, Sura, Colmena',
            'observation' => 'Describa el nivel de riesgo para la prestación del servicio, especialmente si el servicio es de seguridad y salud en el trabajo.',
        ]);
    }
};
