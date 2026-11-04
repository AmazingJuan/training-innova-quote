<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The encuadre is shown as a complete form, like the Excel format: open
 * questions carry an example of what is expected (placeholder) and may be
 * long text (multiline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conditions', function (Blueprint $table) {
            $table->string('placeholder', 500)->nullable();
            $table->boolean('multiline')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('conditions', function (Blueprint $table) {
            $table->dropColumn(['placeholder', 'multiline']);
        });
    }
};
