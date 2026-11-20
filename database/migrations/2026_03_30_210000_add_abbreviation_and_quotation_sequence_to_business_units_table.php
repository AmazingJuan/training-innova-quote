<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->string('abbreviation', 32)->default('')->after('display_name');
            $table->unsignedTinyInteger('quotation_seq_year')->nullable()->after('abbreviation');
            $table->unsignedInteger('quotation_seq_value')->default(0)->after('quotation_seq_year');
        });

        foreach (DB::table('business_units')->orderBy('id')->cursor() as $row) {
            $abbr = strtoupper(trim((string) ($row->abbreviation ?? '')));
            if ($abbr === '') {
                $abbr = 'U'.$row->id;
            }
            DB::table('business_units')->where('id', $row->id)->update(['abbreviation' => $abbr]);
        }

        Schema::table('business_units', function (Blueprint $table) {
            $table->unique('abbreviation');
        });
    }

    public function down(): void
    {
        Schema::table('business_units', function (Blueprint $table) {
            $table->dropUnique(['abbreviation']);
            $table->dropColumn(['abbreviation', 'quotation_seq_year', 'quotation_seq_value']);
        });
    }
};
