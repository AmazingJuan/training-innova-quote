<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The client data of the request is a single set of fields: no separate
 * person the proposal is addressed to, and one phone (the contact's). Only
 * databases that ran an earlier version of the encuadre migrations have
 * these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('quotation_proposal_orders', 'proposal_recipient')) {
            Schema::table('quotation_proposal_orders', function (Blueprint $table) {
                $table->dropColumn('proposal_recipient');
            });
        }

        if (Schema::hasColumn('clients', 'company_phone')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('company_phone');
            });
        }
    }

    public function down(): void
    {
        // Nothing to restore: the columns are no longer part of the schema.
    }
};
