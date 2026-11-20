<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gestion_line_professional', function (Blueprint $table) {
            $table->foreignId('gestion_line_id')->constrained('gestion_lines')->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained()->cascadeOnDelete();
            $table->primary(['gestion_line_id', 'professional_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gestion_line_professional');
    }
};
