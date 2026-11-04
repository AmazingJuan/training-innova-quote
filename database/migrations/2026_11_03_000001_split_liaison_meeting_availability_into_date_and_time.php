<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The availability for the liaison meeting is asked as two fields: a tentative
 * date (a calendar) and a tentative time. The existing question keeps its id
 * and becomes the time; the date is a new question asked right before it, in
 * the place where the availability used to start the branch of "Sí".
 */
return new class extends Migration
{
    private const AVAILABILITY = 'Disponibilidad para la reunión de enlace';
    private const AVAILABILITY_EXAMPLE = 'Ej.: martes y jueves de 2:00 p. m. a 5:00 p. m.';
    private const DATE = 'Fecha tentativa de la reunión de enlace';
    private const TIME = 'Horario tentativo de la reunión de enlace';
    private const TIME_EXAMPLE = 'Ej.: de 2:00 p. m. a 5:00 p. m.';

    public function up(): void
    {
        foreach (DB::table('conditions')->where('label', self::AVAILABILITY)->get() as $availability) {
            $date = DB::table('conditions')->insertGetId([
                'label' => self::DATE,
                'interaction_type' => 'input',
                'type' => 'date',
                'observation' => null,
                'next_condition_id' => $availability->id,
                'allows_multiple_values' => $availability->allows_multiple_values,
                'business_unit_id' => $availability->business_unit_id,
                'multiple' => false,
                'section' => $availability->section,
                'required' => $availability->required,
                'placeholder' => null,
                'multiline' => false,
            ]);

            // Whatever led to the availability (the "Sí" option) now leads to the date first.
            DB::table('condition_options')->where('next_condition_id', $availability->id)->update(['next_condition_id' => $date]);
            DB::table('conditions')->where('next_condition_id', $availability->id)->where('id', '!=', $date)->update(['next_condition_id' => $date]);

            DB::table('conditions')->where('id', $availability->id)->update([
                'label' => self::TIME,
                'placeholder' => self::TIME_EXAMPLE,
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('conditions')->where('label', self::TIME)->get() as $time) {
            $date = DB::table('conditions')->where('label', self::DATE)->where('next_condition_id', $time->id)->first();

            if ($date !== null) {
                DB::table('condition_options')->where('next_condition_id', $date->id)->update(['next_condition_id' => $time->id]);
                DB::table('conditions')->where('next_condition_id', $date->id)->update(['next_condition_id' => $time->id]);
                DB::table('conditions')->where('id', $date->id)->delete();
            }

            DB::table('conditions')->where('id', $time->id)->update([
                'label' => self::AVAILABILITY,
                'placeholder' => self::AVAILABILITY_EXAMPLE,
            ]);
        }
    }
};
