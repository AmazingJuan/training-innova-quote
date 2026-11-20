<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('company_contact_email')->nullable()->after('notification_email');
            $table->string('company_contact_phone', 64)->nullable()->after('company_contact_email');
            $table->text('company_contact_address')->nullable()->after('company_contact_phone');
            $table->string('company_website_url', 512)->nullable()->after('company_contact_address');
            $table->string('company_website_label', 255)->nullable()->after('company_website_url');
        });

        $defaults = [
            'company_contact_email' => 'gerencia@trainingcorporation.com.co',
            'company_contact_phone' => '+57 3217079467',
            'company_contact_address' => 'Carrera 43A No 1 A - Sur 29 Edificio Colmena, oficina 315. Medellín, Antioquia.',
            'company_website_url' => 'https://www.trainingcorporation.com.co',
            'company_website_label' => 'www.trainingcorporation.com.co',
        ];

        if (Schema::hasColumn('configurations', 'application_url')) {
            DB::table('configurations')->update($defaults);
        }

        Schema::table('configurations', function (Blueprint $table) {
            if (Schema::hasColumn('configurations', 'application_url')) {
                $table->dropColumn('application_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn([
                'company_contact_email',
                'company_contact_phone',
                'company_contact_address',
                'company_website_url',
                'company_website_label',
            ]);
        });

        Schema::table('configurations', function (Blueprint $table) {
            $table->string('application_url')->nullable()->after('notification_email');
        });
    }
};
