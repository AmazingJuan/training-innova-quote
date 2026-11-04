<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields that let the questionnaire follow the "formato de encuadre":
 * the block a question belongs to and whether it can be left blank.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conditions', function (Blueprint $table) {
            $table->string('section', 20)->nullable();
            $table->boolean('required')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('conditions', function (Blueprint $table) {
            $table->dropColumn(['section', 'required']);
        });
    }
};
