<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Condition;
use App\Models\GestionLine;
use App\Models\Service;
use Illuminate\Database\Seeder;

class ExampleDataSeeder extends Seeder
{
    /**
     * Seed the application's database with example data for testing.
     */
    public function run(): void
    {
        // =============================================
        // 1. CREAR UNIDADES DE NEGOCIO
        // =============================================
        $auditoria = BusinessUnit::create([
            'name' => 'auditoria',
            'display_name' => 'Auditoría',
            'abbreviation' => 'EO',
        ]);

        $consultoria = BusinessUnit::create([
            'name' => 'consultoria',
            'display_name' => 'Consultoría',
            'abbreviation' => 'CNN',
        ]);

        $formacion = BusinessUnit::create([
            'name' => 'formacion',
            'display_name' => 'Formación',
            'abbreviation' => 'GC',
        ]);

        // =============================================
        // 2. CREAR LÍNEAS DE GESTIÓN
        // =============================================
        $hseq = GestionLine::create(['name' => 'HSEQ']);
        $sst = GestionLine::create(['name' => 'Seguridad y Salud en el Trabajo']);
        $activos = GestionLine::create(['name' => 'Gestión de Activos']);
        $calidad = GestionLine::create(['name' => 'Gestión de Calidad']);
        $ambiental = GestionLine::create(['name' => 'Gestión Ambiental']);
        $riesgos = GestionLine::create(['name' => 'Gestión de Riesgos']);

        // =============================================
        // 3. CREAR SERVICIOS POR UNIDAD DE NEGOCIO
        // =============================================

        // --- AUDITORÍA ---
        // HSEQ
        Service::create(['name' => 'Auditoría ISO 9001:2015', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Auditoría ISO 14001:2015', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Auditoría ISO 45001:2018', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Auditoría Integrada HSEQ', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $hseq->id]);

        // SST
        Service::create(['name' => 'Auditoría SG-SST', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Auditoría de Cumplimiento Legal SST', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $sst->id]);

        // Gestión de Activos
        Service::create(['name' => 'Auditoría ISO 55001', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $activos->id]);

        // Calidad
        Service::create(['name' => 'Auditoría de Segunda Parte', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $calidad->id]);
        Service::create(['name' => 'Auditoría de Procesos', 'business_unit_id' => $auditoria->id, 'gestion_line_id' => $calidad->id]);

        // --- CONSULTORÍA ---
        // HSEQ
        Service::create(['name' => 'Implementación ISO 9001:2015', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Implementación ISO 14001:2015', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Implementación ISO 45001:2018', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Sistema Integrado de Gestión HSEQ', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $hseq->id]);

        // SST
        Service::create(['name' => 'Diseño e Implementación SG-SST', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Matriz de Peligros y Riesgos', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Plan de Emergencias', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Investigación de Accidentes', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $sst->id]);

        // Gestión de Activos
        Service::create(['name' => 'Implementación ISO 55001', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $activos->id]);
        Service::create(['name' => 'Plan de Mantenimiento', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $activos->id]);
        Service::create(['name' => 'Gestión del Ciclo de Vida de Activos', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $activos->id]);

        // Ambiental
        Service::create(['name' => 'Estudios de Impacto Ambiental', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $ambiental->id]);
        Service::create(['name' => 'Plan de Gestión Ambiental', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $ambiental->id]);
        Service::create(['name' => 'Gestión de Residuos', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $ambiental->id]);

        // Riesgos
        Service::create(['name' => 'Implementación ISO 31000', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $riesgos->id]);
        Service::create(['name' => 'Análisis de Riesgos Empresariales', 'business_unit_id' => $consultoria->id, 'gestion_line_id' => $riesgos->id]);

        // --- FORMACIÓN ---
        // HSEQ
        Service::create(['name' => 'Curso Auditor Interno ISO 9001', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Curso Auditor Interno ISO 14001', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Curso Auditor Interno ISO 45001', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $hseq->id]);
        Service::create(['name' => 'Diplomado en Sistemas Integrados HSEQ', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $hseq->id]);

        // SST
        Service::create(['name' => 'Curso de 50 Horas SG-SST', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Curso de 20 Horas SG-SST', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Trabajo en Alturas', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Primeros Auxilios', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Brigadas de Emergencia', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $sst->id]);
        Service::create(['name' => 'Manejo Defensivo', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $sst->id]);

        // Gestión de Activos
        Service::create(['name' => 'Curso ISO 55001', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $activos->id]);
        Service::create(['name' => 'Gestión de Mantenimiento', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $activos->id]);

        // Ambiental
        Service::create(['name' => 'Curso Gestión Ambiental ISO 14001', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $ambiental->id]);
        Service::create(['name' => 'Gestión de Residuos Peligrosos', 'business_unit_id' => $formacion->id, 'gestion_line_id' => $ambiental->id]);

        // =============================================
        // 4. ÁRBOLES DE DECISIÓN (formato de encuadre)
        // =============================================
        $this->call(EncuadreSeeder::class);

        $this->command->info('✅ Datos de ejemplo creados exitosamente:');
        $this->command->info('   - 3 Unidades de Negocio (Auditoría, Consultoría, Formación)');
        $this->command->info('   - 6 Líneas de Gestión (HSEQ, SST, Gestión de Activos, etc.)');
        $this->command->info('   - '.Service::count().' Servicios');
        $this->command->info('   - '.Condition::count().' Condiciones con sus opciones');
    }
}
