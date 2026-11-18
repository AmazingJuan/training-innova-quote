<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The offer date question was found by the beginning of its label: an admin
 * rewording it silently turned off the 3-business-day minimum and the
 * "overdue" reminders. Now the question carries a role, and each request
 * stores the date it asked for in its own column.
 */
return new class extends Migration
{
    private const OFFER_DATE_LABEL = 'fecha estimada de entrega';

    public function up(): void
    {
        Schema::table('conditions', function (Blueprint $table) {
            $table->string('role', 32)->nullable()->after('section');
        });

        DB::table('conditions')
            ->where('type', 'date')
            ->whereRaw('LOWER(label) LIKE ?', [self::OFFER_DATE_LABEL.'%'])
            ->update(['role' => 'offer_date']);

        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->date('offer_date')->nullable()->after('answers');
        });

        DB::table('quotation_proposal_orders')->orderBy('id')->each(function (object $order) {
            foreach (json_decode($order->answers, true) ?: [] as $question => $answer) {
                if (str_starts_with(mb_strtolower(trim((string) $question)), self::OFFER_DATE_LABEL)) {
                    $date = DateTimeImmutable::createFromFormat('!d/m/Y', trim((string) $answer));
                    if ($date !== false) {
                        DB::table('quotation_proposal_orders')->where('id', $order->id)->update(['offer_date' => $date->format('Y-m-d')]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotation_proposal_orders', function (Blueprint $table) {
            $table->dropColumn('offer_date');
        });

        Schema::table('conditions', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
