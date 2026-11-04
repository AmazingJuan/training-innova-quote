<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Turn "fake branches" into plain order without changing what clients see:
 * when every option of a single-choice question leads to the same question,
 * that becomes the question's default next; option targets equal to the
 * default are dropped; multi-choice options never branch, so theirs are
 * cleared. Afterwards the editor can show the questionnaire as an ordered list.
 *
 * Self-contained on purpose (query builder only), like any data migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $options = DB::table('condition_options')->get()->groupBy('condition_id');

            foreach (DB::table('conditions')->where('interaction_type', 'options')->get() as $question) {
                $own = $options->get($question->id);
                if ($own === null || $own->isEmpty()) {
                    continue;
                }

                $default = $question->next_condition_id;
                if (! $question->multiple) {
                    $targets = $own->map(fn ($o) => $o->next_condition_id ?? $default)->unique();
                    if ($targets->count() === 1) {
                        $default = $targets->first();
                        DB::table('conditions')->where('id', $question->id)->update(['next_condition_id' => $default]);
                    }
                }

                DB::table('condition_options')
                    ->where('condition_id', $question->id)
                    ->when(! $question->multiple, fn ($q) => $default === null ? $q->whereRaw('1 = 0') : $q->where('next_condition_id', $default))
                    ->update(['next_condition_id' => null]);
            }
        });
    }

    public function down(): void
    {
        // Behaviour-preserving cleanup: nothing to undo.
    }
};
