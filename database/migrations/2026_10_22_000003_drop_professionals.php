<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client no longer picks a professional: the encuadre only asks for the
 * profile the team must have. Professionals, their lines and the preferred
 * professional of each quotation go away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('professional_id');
        });

        Schema::dropIfExists('gestion_line_professional');
        Schema::dropIfExists('professionals');
    }

    public function down(): void
    {
        Schema::create('professionals', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('summary');
            $table->unsignedSmallInteger('years_experience');
            $table->timestamps();
        });

        Schema::create('gestion_line_professional', function (Blueprint $table) {
            $table->foreignId('gestion_line_id')->constrained('gestion_lines')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->primary(['gestion_line_id', 'professional_id']);
        });

        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->foreignId('professional_id')->nullable()->constrained('professionals')->nullOnDelete();
        });
    }
};
