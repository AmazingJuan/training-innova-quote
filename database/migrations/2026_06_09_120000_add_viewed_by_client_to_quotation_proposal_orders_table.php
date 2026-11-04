<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->boolean('viewed_by_client')->default(false)->after('is_generated');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->dropColumn('viewed_by_client');
        });
    }
};
