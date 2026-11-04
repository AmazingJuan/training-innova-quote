<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The prefix of each unit's quotation codes, as the client uses them:
 * Auditoría EO, Consultoría CNN and Formación GC.
 *
 * The order matters: abbreviations are unique and Consultoría takes the one
 * Formación had, so Formación is renamed first. Codes already issued keep the
 * prefix they were issued with.
 */
return new class extends Migration
{
    private const NEW = ['formacion' => 'GC', 'consultoria' => 'CNN', 'auditoria' => 'EO'];

    private const OLD = ['consultoria' => 'CON', 'formacion' => 'CNN', 'auditoria' => 'AUD'];

    public function up(): void
    {
        $this->rename(self::NEW);
    }

    public function down(): void
    {
        $this->rename(self::OLD);
    }

    /**
     * @param  array<string, string>  $abbreviations
     */
    private function rename(array $abbreviations): void
    {
        foreach ($abbreviations as $name => $abbreviation) {
            DB::table('business_units')->where('name', $name)->update(['abbreviation' => $abbreviation]);
        }
    }
};
