<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A company may be classified in more than one risk class by its ARL (for
 * example, by site or by activity), so the client can now pick several of the
 * options instead of just one. The question keeps its options and its place in
 * the flow; only its selection mode changes, in every business unit.
 */
return new class extends Migration
{
    private const RISK_CLASS = 'Nivel de riesgo de la empresa ante la ARL';

    public function up(): void
    {
        DB::table('conditions')->where('label', self::RISK_CLASS)->update(['multiple' => true]);
    }

    public function down(): void
    {
        DB::table('conditions')->where('label', self::RISK_CLASS)->update(['multiple' => false]);
    }
};
