<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->text('quotation_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->string('quotation_url')->nullable()->change();
        });
    }
};
