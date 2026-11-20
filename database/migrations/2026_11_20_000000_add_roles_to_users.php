<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Until now every account could manage the others. From here on only
 * administrators can: management's account becomes the first one and the
 * rest keep everything else in the panel.
 */
return new class extends Migration
{
    private const MANAGEMENT_EMAIL = 'gerencia@trainingcorporation.com.co';

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 16)->default('user')->after('phone');
        });

        DB::table('users')->where('email', self::MANAGEMENT_EMAIL)->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
