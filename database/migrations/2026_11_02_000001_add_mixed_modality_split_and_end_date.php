<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two additions to the "Condiciones de prestación del servicio" block:
 *
 * - "Mixto" now asks how much of the service is on site and how much is
 *   virtual, before the venues and logistics it already shared with
 *   "Presencial".
 * - The expected date becomes a start date plus an end date.
 */
return new class extends Migration
{
    private const MODALITY = 'Modalidad de prestación del servicio';

    private const DATE = 'Fecha estimada para la prestación del servicio';

    private const START = 'Fecha estimada de inicio para la prestación del servicio';

    private const END = 'Fecha estimada final para la prestación del servicio';

    /** The time split asked when the modality is "Mixto", in the order the client answers it. */
    private const SPLIT = [
        ['Tiempo del servicio en modalidad presencial', 'Ej.: 3 días (24 horas) en sede'],
        ['Tiempo del servicio en modalidad virtual', 'Ej.: 2 días (16 horas) por videoconferencia'],
    ];

    public function up(): void
    {
        $this->splitMixedModality();
        $this->addEndDate();
    }

    public function down(): void
    {
        foreach (DB::table('conditions')->where('label', self::END)->get() as $end) {
            // The start date takes over where the end date went, and gets its old label back.
            DB::table('conditions')->where('label', self::START)->where('next_condition_id', $end->id)->update([
                'label' => self::DATE,
                'next_condition_id' => $end->next_condition_id,
            ]);
            DB::table('conditions')->where('id', $end->id)->delete();
        }

        $labels = array_column(self::SPLIT, 0);
        foreach (DB::table('condition_options')->where('label', 'Mixto')->get() as $mixed) {
            $own = DB::table('conditions')
                ->where('business_unit_id', $this->unitOf($mixed->condition_id))
                ->whereIn('label', $labels)
                ->get();

            if ($own->isEmpty()) {
                continue;
            }

            // "Mixto" goes back to sharing the branch of "Presencial" (where the split ended).
            DB::table('condition_options')->where('id', $mixed->id)->update([
                'next_condition_id' => $own->sortByDesc('id')->first()->next_condition_id,
            ]);
            DB::table('conditions')->whereIn('id', $own->pluck('id'))->delete();
        }
    }

    /** Insert the split between the "Mixto" option and the branch it shares with "Presencial". */
    private function splitMixedModality(): void
    {
        foreach (DB::table('conditions')->where('label', self::MODALITY)->get() as $modality) {
            $mixed = DB::table('condition_options')
                ->where('condition_id', $modality->id)
                ->where('label', 'Mixto')
                ->first();

            if ($mixed === null) {
                continue;
            }

            // Built from the end, so each question already knows where it goes: the shared branch.
            $next = $mixed->next_condition_id;
            foreach (array_reverse(self::SPLIT) as [$label, $placeholder]) {
                $next = DB::table('conditions')->insertGetId([
                    'label' => $label,
                    'interaction_type' => 'input',
                    'type' => 'text',
                    'observation' => null,
                    'next_condition_id' => $next,
                    'allows_multiple_values' => false,
                    'business_unit_id' => $modality->business_unit_id,
                    'multiple' => false,
                    'section' => $modality->section,
                    'required' => true,
                    'placeholder' => $placeholder,
                    'multiline' => false,
                ]);
            }

            DB::table('condition_options')->where('id', $mixed->id)->update(['next_condition_id' => $next]);
        }
    }

    /** Rename the expected date as the start one and add the end date right after it. */
    private function addEndDate(): void
    {
        foreach (DB::table('conditions')->where('label', self::DATE)->get() as $start) {
            $end = DB::table('conditions')->insertGetId([
                'label' => self::END,
                'interaction_type' => 'input',
                'type' => 'date',
                'observation' => 'Fecha aproximada de finalización.',
                'next_condition_id' => $start->next_condition_id,
                'allows_multiple_values' => $start->allows_multiple_values,
                'business_unit_id' => $start->business_unit_id,
                'multiple' => false,
                'section' => $start->section,
                'required' => $start->required,
                'placeholder' => null,
                'multiline' => false,
            ]);

            DB::table('conditions')->where('id', $start->id)->update([
                'label' => self::START,
                'next_condition_id' => $end,
            ]);
        }
    }

    private function unitOf(int $conditionId): ?int
    {
        return DB::table('conditions')->where('id', $conditionId)->value('business_unit_id');
    }
};
