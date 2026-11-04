<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The budget is a yes or no question: only "Sí" asks for the amount, where
 * "Definido" used to. "Por definir" and "No disponible" become "No".
 */
return new class extends Migration
{
    private const OLD = 'Presupuesto establecido para la prestación del servicio';

    private const NEW = '¿Cuenta con un presupuesto establecido para la prestación del servicio?';

    public function up(): void
    {
        foreach (DB::table('conditions')->where('label', self::OLD)->get() as $budget) {
            DB::table('condition_options')
                ->where('condition_id', $budget->id)
                ->whereIn('label', ['Por definir', 'No disponible'])
                ->delete();

            // "Definido" keeps pointing at the amount, and is simply renamed.
            DB::table('condition_options')
                ->where('condition_id', $budget->id)
                ->where('label', 'Definido')
                ->update(['label' => 'Sí']);

            // Inserted last so it is shown after "Sí" (options are listed by id).
            DB::table('condition_options')->insert([
                'condition_id' => $budget->id,
                'label' => 'No',
                'is_other' => false,
                'next_condition_id' => null,
                'value' => null,
            ]);

            DB::table('conditions')->where('id', $budget->id)->update(['label' => self::NEW]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('conditions')->where('label', self::NEW)->get() as $budget) {
            DB::table('condition_options')->where('condition_id', $budget->id)->where('label', 'No')->delete();

            DB::table('condition_options')
                ->where('condition_id', $budget->id)
                ->where('label', 'Sí')
                ->update(['label' => 'Definido']);

            foreach (['Por definir', 'No disponible'] as $label) {
                DB::table('condition_options')->insert([
                    'condition_id' => $budget->id,
                    'label' => $label,
                    'is_other' => false,
                    'next_condition_id' => null,
                    'value' => null,
                ]);
            }

            DB::table('conditions')->where('id', $budget->id)->update(['label' => self::OLD]);
        }
    }
};
