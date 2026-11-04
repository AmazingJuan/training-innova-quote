<?php

namespace Database\Seeders;

use App\Repositories\GestionLineRepository;
use Illuminate\Database\Seeder;

class GestionLineTableSeeder extends Seeder
{
    protected $gestionLineRepository;

    /**
     * Run the database seeds.
     */
    public function __construct(GestionLineRepository $gestionLineRepository)
    {
        $this->gestionLineRepository = $gestionLineRepository;
    }

    public function run(): void
    {
        $gestionLineValues = ['Gestión de activos',
            'Sostenibilidad ambiental',
            'Eficiencia energética', 'Gestión calidad',
            'Gestión de SST', 'Servicios regulatorios',
            'HSEQ', 'Seguridad de la información y ciberseguridad',
            'Laboratorio'];

        foreach ($gestionLineValues as $value) {
            $this->gestionLineRepository->create(['name' => $value]);
        }
    }
}
