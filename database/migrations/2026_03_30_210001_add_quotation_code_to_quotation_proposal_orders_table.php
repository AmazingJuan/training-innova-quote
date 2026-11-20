<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->string('quotation_code', 64)->nullable()->after('id');
        });

        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->unique('quotation_code');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->dropUnique(['quotation_code']);
            $table->dropColumn('quotation_code');
        });
    }
};
