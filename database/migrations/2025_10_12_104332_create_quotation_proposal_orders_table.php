<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quotation_proposal_orders', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->json('contact_info');
            $table->json('services');
            $table->string('business_unit');
            $table->json('answers');
            $table->boolean('is_generated')->default(false);
            $table->string('quotation_url')->nullable()->default(null);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_proposal_orders');
    }
};
