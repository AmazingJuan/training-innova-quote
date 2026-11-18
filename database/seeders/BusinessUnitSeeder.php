<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use Illuminate\Database\Seeder;

/**
 * Training Corporation's three business units. Their abbreviation starts the
 * code of each request (EO-V-2601…).
 */
class BusinessUnitSeeder extends Seeder
{
    private const UNITS = [
        ['name' => 'auditoria', 'display_name' => 'Auditoría', 'abbreviation' => 'EO'],
        ['name' => 'consultoria', 'display_name' => 'Consultoría', 'abbreviation' => 'CNN'],
        ['name' => 'formacion', 'display_name' => 'Formación', 'abbreviation' => 'GC'],
    ];

    public function run(): void
    {
        foreach (self::UNITS as $unit) {
            BusinessUnit::query()->firstOrCreate(['name' => $unit['name']], $unit);
        }
    }
}
