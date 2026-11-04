<?php

namespace Database\Seeders;

use App\Models\BusinessUnit;
use App\Models\Condition;
use App\Models\ConditionOption;
use App\Services\DecisionTreeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The quotation request of each business unit, built from the "Formato de
 * reunión de encuadre con el cliente" (Excel). It is the whole request: the
 * client does not pick services from the catalog.
 *
 * Every questionnaire follows the five blocks of the encuadre (necesidad,
 * condiciones, cotización, validación, cierre) with the format's own fields,
 * mostly open and with an example of what is expected. Only what the format
 * itself offers as a choice is a choice: the modality (presencial, virtual,
 * mixto), the logistics costs (sí / no → cuáles), the liaison meeting
 * (sí / no → disponibilidad), the budget and the "cuando aplique" of the
 * certification fields.
 *
 * Each unit only gets the fields that apply to it: Formación asks for the
 * number of attendees instead of employees and processes, and has no
 * certification; the venue and logistics only appear when the service is
 * not purely virtual.
 *
 * The client data (razón social, actividad económica, dirección, teléfono,
 * quién solicita y a quién va dirigida la propuesta) is asked by the form
 * itself, before the encuadre. The date of the meeting, the person from
 * Training Corporation and the internal delivery date are filled in the admin.
 *
 *   php artisan db:seed --class=EncuadreSeeder
 *
 * It deletes EVERY question of EVERY unit and leaves only the encuadre.
 */
class EncuadreSeeder extends Seeder
{
    public function run(DecisionTreeService $trees): void
    {
        $questionnaires = [
            'auditoria' => $this->auditoria(),
            'consultoria' => $this->consultoria(),
            'formacion' => $this->formacion(),
        ];

        DB::transaction(function () use ($questionnaires, $trees) {
            BusinessUnit::query()->update(['initial_condition_id' => null]);
            Condition::query()->delete();

            foreach ($questionnaires as $name => $questions) {
                $unit = BusinessUnit::query()->where('name', $name)->first();

                if ($unit === null) {
                    $this->command?->warn("No existe la unidad «{$name}»: se omite.");

                    continue;
                }

                $unit->update(['initial_condition_id' => $this->build($unit, $questions, null)]);

                $errors = $trees->structuralErrors($trees->allConditions($unit->fresh()), $unit->initial_condition_id);
                if ($errors !== []) {
                    throw new RuntimeException(implode(' ', $errors));
                }

                $this->command?->info("✅ {$unit->getDisplayName()}: {$unit->conditions()->count()} campos del formulario de solicitud.");
            }
        });

        foreach (BusinessUnit::query()->whereNull('initial_condition_id')->get() as $unit) {
            $this->command?->warn("«{$unit->getDisplayName()}» quedó sin formulario de solicitud: no se ofrecerá para cotizar.");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Questionnaires
    |--------------------------------------------------------------------------
    */

    /** @return list<array<string, mixed>> */
    private function auditoria(): array
    {
        return [
            $this->longText(
                'Descripción del servicio a solicitar',
                'necesidad',
                'Ej.: Auditoría interna al sistema integrado de gestión bajo ISO 9001, ISO 14001 e ISO 45001, para verificar su cumplimiento antes de la auditoría de renovación.',
            ),
            $this->longText(
                'Descripción del alcance actual del sistema de gestión',
                'necesidad',
                'Ej.: Diseño, fabricación y montaje de estructuras metálicas en la planta de Itagüí y la sede administrativa de Medellín.',
            ),
            $this->processes(),
            ...$this->employees(),
            $this->certification(),
            $this->teamProfile('Ej.: auditor líder certificado en ISO 45001 con experiencia en el sector eléctrico.', 'Si necesita un perfil o una certificación específica, descríbalo.'),
            ...$this->arl(),
            ...$this->conditions('Fecha estimada de inicio para la prestación del servicio'),
            ...$this->documents(),
            ...$this->closing(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function consultoria(): array
    {
        return [
            $this->longText(
                'Descripción del servicio a solicitar',
                'necesidad',
                'Ej.: Diseño e implementación del sistema de gestión de seguridad y salud en el trabajo (Decreto 1072 y Resolución 0312), hasta dejarlo listo para la certificación ISO 45001.',
            ),
            $this->longText(
                'Descripción del alcance actual del sistema de gestión',
                'necesidad',
                'Ej.: Prestación de servicios de transporte de carga en las sedes de Bogotá y Barranquilla.',
            ),
            $this->processes(),
            ...$this->employees(),
            $this->certification(),
            $this->teamProfile('Ej.: consultor con experiencia en el sector minero y en sistemas integrados de gestión.', 'Si necesita un perfil o una certificación específica, descríbalo.'),
            ...$this->arl(),
            ...$this->conditions('Fecha estimada de inicio para la prestación del servicio'),
            ...$this->documents(),
            ...$this->closing(),
        ];
    }

    /**
     * What sizes a training is the number of attendees: no employees,
     * processes nor certification.
     *
     * @return list<array<string, mixed>>
     */
    private function formacion(): array
    {
        return [
            $this->longText(
                'Descripción del servicio a solicitar',
                'necesidad',
                'Ej.: Curso de trabajo en alturas nivel avanzado para operarios de mantenimiento, con certificación según la Resolución 4272 de 2021.',
                'Indique el tema, el objetivo, a quién va dirigida, duración sugerida y si requiere certificado o constancia.',
            ),
            $this->field('Número de asistentes para formación', 'number', 'necesidad', 'Ej.: 20', 'Número aproximado de participantes. Si serán varios grupos, indique el total.'),
            $this->field('Número de horas estimadas para la formación', 'number', 'necesidad', 'Ej.: 16'),
            $this->teamProfile('Ej.: entrenador certificado en trabajo en alturas con licencia en SST vigente.'),
            ...$this->arl(),
            ...$this->conditions('Fecha estimada de inicio para la prestación del servicio'),
            ...$this->documents(),
            ...$this->closing(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Shared fields
    |--------------------------------------------------------------------------
    */

    /** @return array<string, mixed> */
    private function processes(): array
    {
        return $this->longText(
            'Descripción del nombre de procesos y/o macroprocesos',
            'necesidad',
            'Ej.: 12 procesos agrupados en 4 macroprocesos (estratégicos, misionales, de apoyo y de evaluación).',
            'Describir los procesos que hacen parte de la cotización.',
        );
    }

    /** @return list<array<string, mixed>> */
    private function employees(): array
    {
        return [
            $this->field('Número de empleados fijos', 'number', 'necesidad', 'Ej.: 85'),
            $this->field('Número de contratistas', 'number', 'necesidad', 'Ej.: 10'),
        ];
    }

    /**
     * The four "(cuando aplique)" fields about certification hang from a
     * single question, so they only appear when they apply.
     *
     * @return array<string, mixed>
     */
    private function certification(): array
    {
        return $this->choice('¿Su organización tiene un sistema de gestión certificado o acreditado?', 'necesidad', [
            $this->option('Sí', [
                $this->field('Sistema de gestión certificado en', 'text', 'necesidad', 'Ej.: ISO 9001:2015 e ISO 45001:2018'),
                $this->field('Ente certificador y/o acreditador', 'text', 'necesidad', 'Ej.: ICONTEC, Bureau Veritas, SGS, ONAC'),
                $this->longText('Alcance certificado', 'necesidad', 'Tal como aparece en su certificado.'),
                $this->choice('Tipo de auditoría de tercera parte y/o acreditación', 'necesidad', [
                    'Certificación', 'Seguimiento', 'Renovación', $this->other(),
                ], 'La próxima que tiene programada o para la que se prepara.'),
            ]),
            'No',
        ]);
    }

    /** @return array<string, mixed> */
    private function teamProfile(string $example, string $observation = 'Si necesita un perfil o una certificación específica, descríbalo; si no, escriba «Sin requisito específico».'): array
    {
        return $this->longText(
            'Perfil del equipo requerido para la prestación del servicio',
            'necesidad',
            $example,
            $observation,
        );
    }

    /**
     * The ARL: its name and, right after, the company's risk class.
     *
     * @return list<array<string, mixed>>
     */
    private function arl(): array
    {
        return [
            $this->field('ARL de la empresa', 'text', 'necesidad', 'Ej.: Positiva, Sura, Colmena', 'Describa el nivel de riesgo para la prestación del servicio, especialmente si el servicio es de seguridad y salud en el trabajo.', required: false),
            $this->choice(
                'Nivel de riesgo de la empresa ante la ARL',
                'necesidad',
                ['Riesgo I', 'Riesgo II', 'Riesgo III', 'Riesgo IV', 'Riesgo V'],
                'Clase de riesgo (I a V) en la que su ARL clasifica a la empresa. Es importante sobre todo si el servicio es de seguridad y salud en el trabajo.',
                multiple: true,
                required: false,
            ),
        ];
    }

    /**
     * Modality and, only when part of it is on site, the venues and the
     * logistics costs (and which ones). Then schedule and expected date.
     *
     * @return list<array<string, mixed>>
     */
    private function conditions(string $date): array
    {
        $onSite = [
            $this->longText(
                'Descripción de sedes (lugar y dirección)',
                'condiciones',
                'Ej.: Planta principal – Cra. 50 # 10-20, Itagüí; Bodega – Calle 30 # 65-15, Medellín.',
            ),
            $this->choice('¿Los costos logísticos serán asumidos por el cliente?', 'condiciones', [
                $this->option('Sí', [
                    $this->field('¿Cuáles costos logísticos asumirá el cliente?', 'text', 'condiciones', 'Ej.: transporte, alojamiento y alimentación del equipo.'),
                ]),
                'No',
            ], 'Desplazamiento, alojamiento, alimentación, salón… Lo que no asuma se incluirá en la cotización.'),
        ];

        // Only "Mixto" splits the service in two: how much of it is on site and how much is virtual.
        $mixed = [
            $this->field('Tiempo del servicio en modalidad presencial', 'text', 'condiciones', 'Ej.: 3 días (24 horas) en sede'),
            $this->field('Tiempo del servicio en modalidad virtual', 'text', 'condiciones', 'Ej.: 2 días (16 horas) por videoconferencia'),
        ];

        return [
            $this->choice('Modalidad de prestación del servicio', 'condiciones', [
                $this->option('Presencial', $onSite),
                'Virtual',
                $this->option('Mixto', $mixed, sameAs: 'Presencial'),
            ]),
            $this->field(
                'Descripción de horarios para la prestación del servicio (lunes a viernes)',
                'text',
                'condiciones',
                'Ej.: de 7:00 a. m. a 4:00 p. m., con almuerzo de 12:00 m. a 1:00 p. m.',
            ),
            $this->field($date, 'date', 'condiciones', observation: 'Fecha aproximada de inicio.'),
            $this->field('Fecha estimada final para la prestación del servicio', 'date', 'condiciones', observation: 'Fecha aproximada de finalización.'),
        ];
    }

    /**
     * What to take into account and, right after, the files themselves, which
     * reach the admin's e-mail with the request.
     *
     * @return list<array<string, mixed>>
     */
    private function documents(): array
    {
        return [
            $this->longText(
                'Documentos o información a tener en cuenta para la cotización',
                'cotizacion',
                'Ej.: mapa de procesos, manual del sistema de gestión, organigrama, PEGA, ciclo de vida de los activos.',
                'Indique los documentos con los que cuenta. Puede adjuntarlos en el campo siguiente; de lo contrario, le serán solicitados por correo electrónico durante la revisión de su solicitud.',
                required: false,
            ),
            $this->field(
                'Adjuntar documentos',
                'file',
                'cotizacion',
                observation: 'Hasta 5 archivos de máximo 10 MB cada uno: PDF, Word, Excel, PowerPoint, imágenes JPG o PNG, o ZIP.',
                required: false,
            ),
        ];
    }

    /**
     * Budget, liaison meeting and the date the client expects the offer by.
     *
     * @return list<array<string, mixed>>
     */
    private function closing(): array
    {
        return [
            $this->choice('¿Cuenta con un presupuesto establecido para la prestación del servicio?', 'cotizacion', [
                $this->option('Sí', [
                    $this->field('Valor del presupuesto establecido', 'number', 'cotizacion', 'Ej.: $15.000.000', 'En pesos colombianos (COP)'),
                ]),
                'No',
            ]),
            $this->choice('En caso de precisar las necesidades del servicio, ¿estaría de acuerdo en realizar una reunión de enlace de manera virtual?', 'cierre', [
                $this->option('Sí', [
                    $this->field('Fecha tentativa de la reunión de enlace', 'date', 'cierre'),
                    $this->field('Horario tentativo de la reunión de enlace', 'text', 'cierre', 'Ej.: de 2:00 p. m. a 5:00 p. m.'),
                    $this->longText(
                        'Correos de otras personas que deben participar en la reunión de enlace',
                        'cierre',
                        'Ej.: calidad@empresa.com, gerencia@empresa.com',
                        'La persona de contacto de esta solicitud ya queda invitada. Si otras personas deben participar, escriba sus correos.',
                        required: false,
                    ),
                ]),
                'No',
            ]),
            $this->field('Fecha estimada de entrega de la oferta comercial', 'date', 'cierre', observation: 'Seleccione un día hábil. Haremos lo posible por enviársela antes de esa fecha.'),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Spec helpers
    |--------------------------------------------------------------------------
    */

    /**
     * An open field: 'text', 'longtext', 'number', 'date' or 'file'.
     *
     * @return array<string, mixed>
     */
    private function field(string $label, string $kind, string $section, ?string $placeholder = null, ?string $observation = null, bool $required = true): array
    {
        return compact('label', 'kind', 'section', 'placeholder', 'observation', 'required');
    }

    /** @return array<string, mixed> */
    private function longText(string $label, string $section, string $placeholder, ?string $observation = null, bool $required = true): array
    {
        return $this->field($label, 'longtext', $section, $placeholder, $observation, $required);
    }

    /**
     * @param  list<string|array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function choice(string $label, string $section, array $options, ?string $observation = null, bool $multiple = false, bool $required = true): array
    {
        return [
            ...$this->field($label, $multiple ? 'multiple' : 'single', $section, null, $observation, $required),
            'options' => array_map(fn ($option) => is_string($option) ? $this->option($option) : $option, $options),
        ];
    }

    /**
     * An option, optionally opening a branch of fields (after which the client
     * continues with what follows the parent), or leading to the same branch
     * as a sibling option. With both, its own fields come first and then the
     * sibling's branch.
     *
     * @param  list<array<string, mixed>>  $branch
     * @return array<string, mixed>
     */
    private function option(string $label, array $branch = [], ?string $sameAs = null, bool $isOther = false): array
    {
        return ['label' => $label, 'branch' => $branch, 'same_as' => $sameAs, 'is_other' => $isOther];
    }

    /** @return array<string, mixed> */
    private function other(string $label = 'Otro'): array
    {
        return $this->option($label, isOther: true);
    }

    /*
    |--------------------------------------------------------------------------
    | Builder
    |--------------------------------------------------------------------------
    */

    /**
     * Create a chain of questions (and their branches) that ends in
     * `$continueTo`, and return the id of its first question. Built from the
     * end so each question already knows where it goes.
     *
     * @param  list<array<string, mixed>>  $questions
     */
    private function build(BusinessUnit $unit, array $questions, ?int $continueTo): ?int
    {
        $next = $continueTo;

        foreach (array_reverse($questions) as $spec) {
            $kind = $spec['kind'];
            $isChoice = in_array($kind, ['single', 'multiple'], true);

            $question = Condition::create([
                'business_unit_id' => $unit->id,
                'label' => $spec['label'],
                'interaction_type' => $isChoice ? 'options' : 'input',
                'type' => match ($kind) {
                    'number', 'date', 'file' => $kind,
                    default => 'text',
                },
                'multiline' => $kind === 'longtext',
                'multiple' => $kind === 'multiple',
                'placeholder' => $spec['placeholder'] ?? null,
                'observation' => $spec['observation'] ?? null,
                'section' => $spec['section'],
                'required' => $spec['required'] ?? true,
                'next_condition_id' => $next,
            ]);

            $branchStarts = [];
            foreach ($spec['options'] ?? [] as $option) {
                if ($option['branch'] !== [] && $kind === 'multiple') {
                    throw new RuntimeException("«{$spec['label']}»: las preguntas de varias opciones no pueden abrir ramas.");
                }

                // Where this option's own fields rejoin: a sibling's branch, or what follows the question.
                $join = $option['same_as'] !== null
                    ? ($branchStarts[$option['same_as']] ?? throw new RuntimeException("«{$option['label']}» remite a una opción que no existe o va después."))
                    : null;
                $target = $option['branch'] !== []
                    ? $this->build($unit, $option['branch'], $join ?? $next)
                    : $join;
                $branchStarts[$option['label']] = $target;

                ConditionOption::create([
                    'condition_id' => $question->id,
                    'label' => $option['label'],
                    'is_other' => $option['is_other'],
                    'next_condition_id' => $target,
                ]);
            }

            $next = $question->id;
        }

        return $next;
    }
}
