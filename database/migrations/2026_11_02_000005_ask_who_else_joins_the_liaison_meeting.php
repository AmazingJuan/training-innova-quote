<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * When the client agrees to the liaison meeting, they may name who else should
 * be invited: the contact of the request is invited anyway, these are the rest.
 */
return new class extends Migration
{
    private const AVAILABILITY = 'Disponibilidad para la reunión de enlace';

    private const GUESTS = 'Correos de otras personas que deben participar en la reunión de enlace';

    public function up(): void
    {
        foreach (DB::table('conditions')->where('label', self::AVAILABILITY)->get() as $availability) {
            // Asked right after the availability, and continues where it went.
            $guests = DB::table('conditions')->insertGetId([
                'label' => self::GUESTS,
                'interaction_type' => 'input',
                'type' => 'text',
                'observation' => 'La persona de contacto de esta solicitud ya queda invitada. Si otras personas deben participar, escriba sus correos.',
                'next_condition_id' => $availability->next_condition_id,
                'allows_multiple_values' => $availability->allows_multiple_values,
                'business_unit_id' => $availability->business_unit_id,
                'multiple' => false,
                'section' => $availability->section,
                'required' => false,
                'placeholder' => 'Ej.: calidad@empresa.com, gerencia@empresa.com',
                'multiline' => true,
            ]);

            DB::table('conditions')->where('id', $availability->id)->update(['next_condition_id' => $guests]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('conditions')->where('label', self::GUESTS)->get() as $guests) {
            DB::table('conditions')
                ->where('label', self::AVAILABILITY)
                ->where('next_condition_id', $guests->id)
                ->update(['next_condition_id' => $guests->next_condition_id]);

            DB::table('conditions')->where('id', $guests->id)->delete();
        }
    }
};
