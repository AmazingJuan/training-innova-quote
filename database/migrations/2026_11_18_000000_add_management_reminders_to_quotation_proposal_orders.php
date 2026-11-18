<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many reminders management has received about a pending request, so the
 * reminder command sends each one once (see quotations:remind-management).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->unsignedSmallInteger('reminders_sent')->default(0)->after('quotation_url');
            $table->timestamp('last_reminded_at')->nullable()->after('reminders_sent');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->dropColumn(['reminders_sent', 'last_reminded_at']);
        });
    }
};
