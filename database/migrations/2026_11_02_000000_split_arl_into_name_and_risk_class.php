<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The ARL is asked in two questions: its name ("ARL de la empresa", as it
 * always was) and, right after, the company's risk class as a choice from
 * I to V. Starts from the single "Nivel de riesgo" text question left by
 * 2026_10_28_000001_ask_arl_risk_class.
 */
return new class extends Migration
{
    private const NAME = 'ARL de la empresa';

    private const RISK = 'Nivel de riesgo de la empresa ante la ARL';

    private const LEVELS = ['Riesgo I', 'Riesgo II', 'Riesgo III', 'Riesgo IV', 'Riesgo V'];

    public function up(): void
    {
        foreach (DB::table('conditions')->where('label', self::RISK)->where('interaction_type', 'input')->get() as $old) {
            // The risk class goes right after the name, and continues where the old question went.
            $risk = DB::table('conditions')->insertGetId([
                'label' => self::RISK,
                'interaction_type' => 'options',
                'type' => 'text',
                'observation' => 'Clase de riesgo (I a V) en la que su ARL clasifica a la empresa. Es importante sobre todo si el servicio es de seguridad y salud en el trabajo.',
                'next_condition_id' => $old->next_condition_id,
                'allows_multiple_values' => $old->allows_multiple_values,
                'business_unit_id' => $old->business_unit_id,
                'multiple' => false,
                'section' => $old->section,
                'required' => $old->required,
                'placeholder' => null,
                'multiline' => false,
            ]);

            foreach (self::LEVELS as $level) {
                // No next_condition_id: each option continues with the question's own next question.
                DB::table('condition_options')->insert([
                    'condition_id' => $risk,
                    'label' => $level,
                    'is_other' => false,
                    'next_condition_id' => null,
                    'value' => null,
                ]);
            }

            DB::table('conditions')->where('id', $old->id)->update([
                'label' => self::NAME,
                'placeholder' => 'Ej.: Positiva, Sura, Colmena',
                'observation' => 'Describa el nivel de riesgo para la prestación del servicio, especialmente si el servicio es de seguridad y salud en el trabajo.',
                'next_condition_id' => $risk,
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('conditions')->where('label', self::RISK)->where('interaction_type', 'options')->get() as $risk) {
            DB::table('condition_options')->where('condition_id', $risk->id)->delete();

            // The name question goes back to being the single "Nivel de riesgo" text question.
            DB::table('conditions')->where('label', self::NAME)->where('next_condition_id', $risk->id)->update([
                'label' => self::RISK,
                'placeholder' => 'Ej.: Riesgo III',
                'observation' => 'Clase de riesgo (I a V) en la que su ARL clasifica a la empresa. Es importante sobre todo si el servicio es de seguridad y salud en el trabajo.',
                'next_condition_id' => $risk->next_condition_id,
            ]);

            DB::table('conditions')->where('id', $risk->id)->delete();
        }
    }
};
