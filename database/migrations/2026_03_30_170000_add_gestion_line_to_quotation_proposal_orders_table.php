<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('quotation_proposal_orders', 'gestion_line')) {
                $table->string('gestion_line')->nullable()->after('business_unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            if (Schema::hasColumn('quotation_proposal_orders', 'gestion_line')) {
                $table->dropColumn('gestion_line');
            }
        });
    }
};
