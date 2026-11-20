<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * What sizes a training is the number of attendees and how long it lasts, so
 * the hours are asked right next to the attendees.
 */
return new class extends Migration
{
    private const ATTENDEES = 'Número de asistentes para formación';

    private const HOURS = 'Número de horas estimadas para la formación';

    public function up(): void
    {
        foreach (DB::table('conditions')->where('label', self::ATTENDEES)->get() as $attendees) {
            $hours = DB::table('conditions')->insertGetId([
                'label' => self::HOURS,
                'interaction_type' => 'input',
                'type' => 'number',
                'observation' => null,
                'next_condition_id' => $attendees->next_condition_id,
                'allows_multiple_values' => $attendees->allows_multiple_values,
                'business_unit_id' => $attendees->business_unit_id,
                'multiple' => false,
                'section' => $attendees->section,
                'required' => $attendees->required,
                'placeholder' => 'Ej.: 16',
                'multiline' => false,
            ]);

            DB::table('conditions')->where('id', $attendees->id)->update(['next_condition_id' => $hours]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('conditions')->where('label', self::HOURS)->get() as $hours) {
            DB::table('conditions')
                ->where('label', self::ATTENDEES)
                ->where('next_condition_id', $hours->id)
                ->update(['next_condition_id' => $hours->next_condition_id]);

            DB::table('conditions')->where('id', $hours->id)->delete();
        }
    }
};
