<?php

/**
 * Datos de demostración para las capturas del manual.
 *
 * Trabaja sobre la base del entorno de capturas (ver entorno.sh y mariadb.sh):
 * la recrea desde cero, carga las unidades, servicios y formularios del
 * ExampleDataSeeder y agrega usuarios, clientes y solicitudes ficticias.
 *
 *   bash -c 'source manual-admin/herramientas/entorno.sh && php manual-admin/herramientas/datos-demo.php'
 */

use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\Configuration;
use App\Models\QuotationProposalOrder;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Protección: solo trabaja sobre la base de las capturas, nunca sobre la de desarrollo.
if (config('database.connections.mysql.database') !== 'manual_capturas' || (string) config('database.connections.mysql.port') !== '3307') {
    fwrite(STDERR, "Este script solo se ejecuta sobre la base de las capturas. Cárguelo con bash después de entorno.sh.\n");
    exit(1);
}

Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--class' => 'ExampleDataSeeder', '--force' => true]);

// ---------------------------------------------------------------------
// Usuarios del panel
// ---------------------------------------------------------------------
$usuarios = [
    ['Laura Restrepo', 'laura.restrepo@trainingcorporation.com.co', '3104567890'],
    ['Andrés Mejía', 'andres.mejia@trainingcorporation.com.co', '3157654321'],
    ['Paula Zapata', 'paula.zapata@trainingcorporation.com.co', '3009876543'],
];
foreach ($usuarios as [$nombre, $correo, $telefono]) {
    User::create(['name' => $nombre, 'email' => $correo, 'password' => Hash::make('Manual2026*'), 'phone' => $telefono]);
}

// ---------------------------------------------------------------------
// Configuración (la misma del entorno local del proyecto)
// ---------------------------------------------------------------------
Configuration::current()->update([
    'notification_email' => 'gerencia@trainingcorporation.com.co',
    'company_contact_email' => 'gerencia@trainingcorporation.com.co',
    'company_contact_phone' => '+57 3217079467',
    'company_contact_address' => 'Carrera 43A No 1 A - Sur 29 Edificio Colmena, oficina 315. Medellín, Antioquia.',
    'company_website_url' => 'https://www.trainingcorporation.com.co',
    'company_website_label' => 'www.trainingcorporation.com.co',
]);

// ---------------------------------------------------------------------
// Respuestas base por unidad (el recorrido sigue las ramas elegidas)
// ---------------------------------------------------------------------
$comunes = [
    'Nivel de riesgo de la empresa ante la ARL' => 'Riesgo III',
    'ARL de la empresa' => 'Sura',
    'Modalidad de prestación del servicio' => 'Presencial',
    'Descripción de sedes (lugar y dirección)' => 'Planta principal: Cra. 50 # 10-20, Itagüí. Sede administrativa: Calle 30 # 65-15, Medellín.',
    '¿Los costos logísticos serán asumidos por el cliente?' => 'No',
    '¿Cuáles costos logísticos asumirá el cliente?' => 'Transporte y alimentación del equipo.',
    'Tiempo del servicio en modalidad presencial' => '3 días',
    'Tiempo del servicio en modalidad virtual' => '2 días',
    'Descripción de horarios para la prestación del servicio (lunes a viernes)' => 'De 7:00 a. m. a 4:00 p. m., con almuerzo de 12:00 m. a 1:00 p. m.',
    'Fecha estimada de inicio para la prestación del servicio' => '18/1/2027',
    'Fecha estimada final para la prestación del servicio' => '29/1/2027',
    'Documentos o información a tener en cuenta para la cotización' => 'Mapa de procesos y manual del sistema de gestión.',
    'Adjuntar documentos' => 'Mapa de procesos.pdf, Manual del sistema de gestión.pdf',
    '¿Cuenta con un presupuesto establecido para la prestación del servicio?' => 'Sí',
    'Valor del presupuesto establecido' => '15.000.000',
    'En caso de precisar las necesidades del servicio, ¿estaría de acuerdo en realizar una reunión de enlace?' => 'Sí',
    'Disponibilidad para la reunión de enlace' => 'Martes y jueves de 2:00 p. m. a 5:00 p. m.',
    'Correos de otras personas que deben participar en la reunión de enlace' => '',
    'Fecha estimada de entrega de la oferta comercial' => '4/12/2026',
];

$base = [
    'auditoria' => $comunes + [
        'Descripción del servicio a solicitar' => 'Auditoría interna al sistema integrado de gestión bajo ISO 9001, ISO 14001 e ISO 45001, para verificar su cumplimiento antes de la auditoría de renovación.',
        'Descripción del alcance actual del sistema de gestión' => 'Diseño, fabricación y montaje de estructuras metálicas en la planta de Itagüí y la sede administrativa de Medellín.',
        'Descripción del nombre de procesos y/o macroprocesos' => 'Direccionamiento estratégico, Comercial, Diseño, Producción, Montaje, Compras, Gestión humana y Mejora continua.',
        'Número de empleados fijos' => '85',
        'Número de contratistas' => '12',
        '¿Su organización tiene un sistema de gestión certificado o acreditado?' => 'Sí',
        'Sistema de gestión certificado en' => 'ISO 9001:2015 e ISO 45001:2018',
        'Ente certificador y/o acreditador' => 'ICONTEC',
        'Alcance certificado' => 'Diseño, fabricación y montaje de estructuras metálicas.',
        'Tipo de auditoría de tercera parte y/o acreditación' => 'Renovación',
        'Perfil del equipo requerido para la prestación del servicio' => 'Auditor líder certificado en ISO 45001 con experiencia en el sector metalmecánico.',
    ],
    'consultoria' => $comunes + [
        'Descripción del servicio a solicitar' => 'Diseño e implementación del sistema de gestión de seguridad y salud en el trabajo (Decreto 1072 y Resolución 0312), hasta dejarlo listo para la certificación ISO 45001.',
        'Descripción del alcance actual del sistema de gestión' => 'Prestación de servicios de transporte de carga en las sedes de Bogotá y Barranquilla.',
        'Descripción del nombre de procesos y/o macroprocesos' => 'Planeación, Operación de transporte, Mantenimiento de flota, Gestión humana, Compras y Servicio al cliente.',
        'Número de empleados fijos' => '140',
        'Número de contratistas' => '35',
        '¿Su organización tiene un sistema de gestión certificado o acreditado?' => 'No',
        'Perfil del equipo requerido para la prestación del servicio' => 'Consultor con licencia en SST y experiencia en empresas de transporte.',
    ],
    'formacion' => $comunes + [
        'Descripción del servicio a solicitar' => 'Curso de trabajo en alturas nivel avanzado para operarios de mantenimiento, con certificación según la Resolución 4272 de 2021.',
        'Número de asistentes para formación' => '20',
        'Perfil del equipo requerido para la prestación del servicio' => 'Entrenador certificado en trabajo en alturas con licencia en SST vigente.',
        'Modalidad de prestación del servicio' => 'Mixto',
    ],
];

/** Recorre el formulario de la unidad como lo haría el cliente y devuelve sus respuestas. */
$responder = function (BusinessUnit $unidad, array $valores): array {
    $preguntas = $unidad->conditions()->with('options')->get()->keyBy('id');
    $respuestas = [];
    $vistas = [];
    $id = $unidad->initial_condition_id;

    while ($id !== null && isset($preguntas[$id]) && ! isset($vistas[$id])) {
        $vistas[$id] = true;
        $pregunta = $preguntas[$id];
        $siguiente = $pregunta->next_condition_id;
        $valor = $valores[$pregunta->label] ?? null;

        if ($pregunta->interaction_type === 'options' && $pregunta->options->isNotEmpty()) {
            $opcion = $pregunta->options->firstWhere('label', $valor) ?? $pregunta->options->first();
            $valor = $opcion->label;
            if (! $pregunta->multiple && $opcion->next_condition_id) {
                $siguiente = $opcion->next_condition_id;
            }
        }

        if ($valor !== null && $valor !== '') {
            $respuestas[$pregunta->label] = $valor;
        }
        $id = $siguiente;
    }

    return $respuestas;
};

// ---------------------------------------------------------------------
// Clientes y solicitudes ficticias, en orden cronológico
// ---------------------------------------------------------------------
$carolina = ['name' => 'Carolina Ríos', 'company' => 'Estructuras Metálicas del Valle S.A.S.', 'nit' => '900456789-1', 'economic_activity' => 'Fabricación de productos metálicos para uso estructural', 'address' => 'Cra. 50 # 10-20, Itagüí, Antioquia', 'email' => 'carolina.rios@estructurasdelvalle.com.co', 'phone' => '3104455667', 'role' => 'Directora de calidad'];

$solicitudes = [
    ['auditoria', '2026-10-14 09:12', 'vista', $carolina, []],
    ['consultoria', '2026-10-16 15:40', 'vista', ['name' => 'Juan Pablo Ortiz', 'company' => 'Transportes Andinos S.A.', 'nit' => '890321654-2', 'economic_activity' => 'Transporte de carga por carretera', 'address' => 'Calle 13 # 68-40, Bogotá D. C.', 'email' => 'jportiz@transportesandinos.com.co', 'phone' => '3187766554', 'role' => 'Coordinador SST'], []],
    ['formacion', '2026-10-20 11:05', 'no-vista', ['name' => 'Marcela Duque', 'company' => 'Constructora Altos del Río S.A.S.', 'nit' => '901234567-8', 'economic_activity' => 'Construcción de edificios residenciales', 'address' => 'Av. Las Vegas # 25 Sur-30, Envigado, Antioquia', 'email' => 'mduque@altosdelrio.com.co', 'phone' => '3012233445', 'role' => 'Jefe de gestión humana'], []],
    ['auditoria', '2026-10-23 08:30', 'vista', ['name' => 'Felipe Arango', 'company' => 'Alimentos La Pradera S.A.S.', 'nit' => '900987654-3', 'economic_activity' => 'Elaboración de productos lácteos', 'address' => 'Km 2 vía Rionegro - La Ceja, Rionegro, Antioquia', 'email' => 'farango@alimentoslapradera.com.co', 'phone' => '3145566778', 'role' => 'Gerente general'], ['Número de empleados fijos' => '210', 'Tipo de auditoría de tercera parte y/o acreditación' => 'Seguimiento']],
    ['formacion', '2026-10-27 14:20', 'pendiente', $carolina, ['Número de asistentes para formación' => '15', 'Modalidad de prestación del servicio' => 'Presencial']],
    ['consultoria', '2026-10-28 10:15', 'pendiente', ['name' => 'Daniela Cárdenas', 'company' => 'Clínica San Rafael IPS', 'nit' => '890112233-4', 'economic_activity' => 'Actividades de hospitales y clínicas', 'address' => 'Calle 51 # 45-20, Medellín, Antioquia', 'email' => 'dcardenas@clinicasanrafael.com.co', 'phone' => '3206677889', 'role' => 'Líder de calidad'], ['Modalidad de prestación del servicio' => 'Virtual']],
    ['auditoria', '2026-10-30 16:45', 'no-vista', ['name' => 'Santiago Herrera', 'company' => 'Energía Solar del Caribe S.A.S. E.S.P.', 'nit' => '901555666-7', 'economic_activity' => 'Generación de energía eléctrica', 'address' => 'Vía 40 # 79-115, Barranquilla, Atlántico', 'email' => 'sherrera@energiasolarcaribe.com.co', 'phone' => '3008899001', 'role' => 'Director HSEQ'], []],
    ['consultoria', '2026-11-01 09:50', 'pendiente', ['name' => 'Valentina Gómez', 'company' => 'Laboratorios Biocare S.A.S.', 'nit' => '900777888-9', 'economic_activity' => 'Fabricación de productos farmacéuticos', 'address' => 'Calle 10 Sur # 50FF-28, Medellín, Antioquia', 'email' => 'vgomez@biocare.com.co', 'phone' => '3123344556', 'role' => 'Jefe de aseguramiento de calidad'], []],
    ['formacion', '2026-11-02 10:45', 'pendiente', ['name' => 'Andrés Villa', 'company' => 'Logística Express del Norte S.A.S.', 'nit' => '901888999-0', 'economic_activity' => 'Almacenamiento y depósito', 'address' => 'Carrera 52 # 125-40, Bello, Antioquia', 'email' => 'avilla@logisticaexpressnorte.com.co', 'phone' => '3016677889', 'role' => 'Coordinador de operaciones'], ['Correos de otras personas que deben participar en la reunión de enlace' => 'sst@logisticaexpressnorte.com.co, gestionhumana@logisticaexpressnorte.com.co']],
];

foreach ($solicitudes as [$nombreUnidad, $fecha, $estado, $contacto, $ajustes]) {
    $cuando = Carbon::parse($fecha, 'America/Bogota');
    Carbon::setTestNow($cuando);

    $orden = DB::transaction(function () use ($nombreUnidad, $contacto, $ajustes, $base, $responder) {
        $unidad = BusinessUnit::query()->where('name', $nombreUnidad)->firstOrFail();

        return QuotationProposalOrder::create([
            'quotation_code' => $unidad->lockAndAllocateNextQuotationCode(),
            'client_id' => Client::upsertFromContact($contacto)->getId(),
            'services' => [],
            'business_unit' => $unidad->display_name,
            'answers' => $responder($unidad, $ajustes + $base[$nombreUnidad]),
        ]);
    });

    if ($estado !== 'pendiente') {
        $orden->forceFill([
            'quotation_url' => 'https://drive.google.com/file/d/1aB2cD3eF4gH5iJ6kL7mN8oP/view',
            'is_generated' => true,
            'viewed_by_client' => $estado === 'vista',
        ])->save();
    }
}
Carbon::setTestNow();

echo 'Usuarios: '.User::count().' · Clientes: '.Client::count().' · Solicitudes: '.QuotationProposalOrder::count()."\n";
foreach (QuotationProposalOrder::query()->orderBy('created_at')->get() as $orden) {
    echo $orden->quotation_code.' '.$orden->created_at.' '.count($orden->answers ?? []).' respuestas'.($orden->is_generated ? ' (generada)' : '')."\n";
}
