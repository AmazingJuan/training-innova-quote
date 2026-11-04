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
        Schema::create('conditions', function (Blueprint $table) {
            $table->id();
            $table->string('label');

            // How does user interacts
            $table->enum('interaction_type', [
                'input',    // free input
                'options',  // selection
                'range',     // range
            ]);

            // Data type (only applies to input and range (when used with number and date))
            $table->enum('type', ['text', 'number', 'date'])->default('text');

            $table->text('observation')->nullable();

            $table->foreignId('next_condition_id')
                ->nullable()
                ->constrained('conditions')
                ->onDelete('set null');

            $table->boolean('allows_multiple_values')->default(false);

            $table->foreignId('business_unit_id')->constrained()->onDelete('cascade');
        });

        Schema::table('business_units', function (Blueprint $table) {
            $table->foreignId('initial_condition_id')
                ->nullable()
                ->constrained('conditions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First remove the foreign key/column added to business_units
        Schema::table('business_units', function (Blueprint $table) {
            if (Schema::hasColumn('business_units', 'initial_condition_id')) {
                if (method_exists($table, 'dropConstrainedForeignId')) {
                    $table->dropConstrainedForeignId('initial_condition_id');
                } else {
                    $table->dropForeign(['initial_condition_id']);
                    $table->dropColumn('initial_condition_id');
                }
            }
        });

        Schema::dropIfExists('conditions');
    }
};
