<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Since the request is the encuadre form, the client no longer picks services
 * from a catalog: services and management lines go away, with the columns
 * that stored them on each request.
 *
 * A request now points to its business unit by id instead of copying its
 * name, so renaming a unit no longer breaks the filters or the history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->foreignId('business_unit_id')->nullable()->after('client_id')->constrained()->restrictOnDelete();
        });

        DB::table('quotation_proposal_orders')->orderBy('id')->each(function (object $order) {
            $unitId = DB::table('business_units')->where('display_name', $order->business_unit)->value('id');
            DB::table('quotation_proposal_orders')->where('id', $order->id)->update(['business_unit_id' => $unitId]);
        });

        $orphans = DB::table('quotation_proposal_orders')->whereNull('business_unit_id')->count();
        if ($orphans > 0) {
            throw new RuntimeException("{$orphans} solicitudes no corresponden a ninguna unidad de negocio; corríjalas antes de migrar.");
        }

        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->foreignId('business_unit_id')->nullable(false)->change();
            $table->dropColumn(['business_unit', 'services', 'gestion_line']);
        });

        Schema::dropIfExists('services');
        Schema::dropIfExists('gestion_lines');
    }

    public function down(): void
    {
        Schema::create('gestion_lines', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('business_unit_id')->constrained();
            $table->foreignId('gestion_line_id')->constrained();
        });

        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->string('business_unit')->default('');
            $table->json('services')->nullable();
            $table->string('gestion_line')->nullable();
        });

        DB::table('quotation_proposal_orders')->orderBy('id')->each(function (object $order) {
            DB::table('quotation_proposal_orders')->where('id', $order->id)->update([
                'business_unit' => DB::table('business_units')->where('id', $order->business_unit_id)->value('display_name'),
            ]);
        });

        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_unit_id');
        });
    }
};
