<?php

namespace Tests\Feature;

use App\Models\BusinessUnit;
use App\Models\Condition;
use App\Services\DecisionTreeService;
use Database\Seeders\EncuadreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncuadreSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['auditoria' => 'Auditoría', 'consultoria' => 'Consultoría', 'formacion' => 'Formación'] as $name => $display) {
            BusinessUnit::create(['name' => $name, 'display_name' => $display, 'abbreviation' => strtoupper(substr($name, 0, 3))]);
        }

        $this->seed(EncuadreSeeder::class);
    }

    public function test_every_unit_gets_a_valid_encuadre_split_in_blocks(): void
    {
        $trees = app(DecisionTreeService::class);

        foreach (['auditoria', 'consultoria', 'formacion'] as $name) {
            $unit = $this->unit($name);
            $conditions = $trees->allConditions($unit);

            $this->assertNotEmpty($conditions);
            $this->assertSame([], $trees->structuralErrors($conditions, $unit->initial_condition_id), $name);
            $this->assertCount(count($conditions), $trees->reachableTree($unit), "every field of {$name} is reachable");
            $this->assertSame([], array_values(array_filter($conditions, fn ($c) => $c['section'] === null)), "every field of {$name} has a block");

            $labels = array_column($conditions, 'label');
            $this->assertSame($labels, array_unique($labels), "labels of {$name} are unique (answers are keyed by them)");

            $first = Condition::find($unit->initial_condition_id);
            $this->assertSame('Descripción del servicio a solicitar', $first->label);
            $this->assertTrue($first->isMultiline());
            $this->assertNotEmpty($first->getPlaceholder(), 'the description says what is expected');
        }
    }

    public function test_previous_questions_of_every_unit_are_deleted(): void
    {
        $other = BusinessUnit::create(['name' => 'innovacion', 'display_name' => 'Innovación', 'abbreviation' => 'INN']);
        $old = Condition::create(['label' => 'Pregunta vieja', 'interaction_type' => 'input', 'type' => 'text', 'business_unit_id' => $other->id]);
        $other->update(['initial_condition_id' => $old->id]);
        $auditoria = $this->unit('auditoria')->conditions()->pluck('id')->all();

        $this->seed(EncuadreSeeder::class);

        $this->assertModelMissing($old);
        $this->assertNull($other->fresh()->initial_condition_id);
        $this->assertSame([], array_intersect($auditoria, $this->unit('auditoria')->conditions()->pluck('id')->all()), 'replaced, not kept');
    }

    public function test_open_fields_carry_an_example_and_only_the_format_choices_are_choices(): void
    {
        $choices = $this->unit('auditoria')->conditions()->where('interaction_type', 'options')->pluck('label')->all();
        sort($choices);

        $this->assertSame([
            'En caso de precisar las necesidades del servicio, ¿estaría de acuerdo en realizar una reunión de enlace?',
            'Modalidad de prestación del servicio',
            'Presupuesto establecido para la prestación del servicio',
            'Tipo de auditoría de tercera parte y/o acreditación',
            '¿Los costos logísticos serán asumidos por el cliente?',
            '¿Su organización tiene un sistema de gestión certificado o acreditado?',
        ], $choices);

        $withoutExample = $this->unit('auditoria')->conditions()
            ->where('interaction_type', 'input')->where('type', 'text')->whereNull('placeholder')->pluck('label')->all();
        $this->assertSame([], $withoutExample);
    }

    public function test_training_asks_for_attendees_instead_of_employees_processes_or_certification(): void
    {
        $formacion = $this->labels('formacion');
        $this->assertContains('Número de asistentes para formación', $formacion);
        $this->assertNotContains('Número de empleados de la empresa', $formacion);
        $this->assertNotContains('Descripción del nombre de procesos y/o macroprocesos', $formacion);
        $this->assertNotContains('¿Su organización tiene un sistema de gestión certificado o acreditado?', $formacion);
        $this->assertContains('Documentos o información a tener en cuenta para la cotización', $formacion);

        foreach (['auditoria', 'consultoria'] as $name) {
            $labels = $this->labels($name);
            $this->assertContains('Número de empleados de la empresa', $labels);
            $this->assertNotContains('Número de asistentes para formación', $labels);
        }
    }

    public function test_every_unit_can_attach_documents_right_after_describing_them(): void
    {
        foreach (['auditoria', 'consultoria', 'formacion'] as $name) {
            $description = $this->question($name, 'Documentos o información a tener en cuenta para la cotización');
            $files = Condition::find($description->next_condition_id);

            $this->assertSame('Adjuntar documentos', $files->label, $name);
            $this->assertSame('input', $files->interaction_type);
            $this->assertSame('file', $files->type);
            $this->assertSame('cotizacion', $files->getSection());
            $this->assertFalse($files->isRequired());
            $this->assertSame('Presupuesto establecido para la prestación del servicio', $files->getNextCondition()->label);
        }
    }

    public function test_certification_fields_only_appear_when_certified(): void
    {
        $gate = $this->question('auditoria', '¿Su organización tiene un sistema de gestión certificado o acreditado?');
        $options = $gate->options()->pluck('next_condition_id', 'label');
        $this->assertNull($options['No']);

        $chain = [];
        for ($id = $options['Sí']; $id !== $gate->next_condition_id; $id = Condition::find($id)->next_condition_id) {
            $chain[] = Condition::find($id)->label;
        }
        $this->assertSame([
            'Sistema de gestión certificado en',
            'Ente certificador y/o acreditador',
            'Alcance certificado',
            'Tipo de auditoría de tercera parte y/o acreditación',
        ], $chain);
    }

    public function test_venue_and_logistics_are_skipped_for_virtual_services(): void
    {
        foreach (['auditoria', 'formacion'] as $name) {
            $modality = $this->question($name, 'Modalidad de prestación del servicio');
            $options = $modality->options()->pluck('next_condition_id', 'label');
            $schedule = $this->question($name, 'Descripción de horarios para la prestación del servicio (lunes a viernes)');

            $this->assertSame($schedule->id, $modality->next_condition_id);
            $this->assertNull($options['Virtual']);
            $this->assertSame($options['Presencial'], $options['Mixto']);

            $venue = Condition::find($options['Presencial']);
            $this->assertSame('Descripción de sedes (lugar y dirección)', $venue->label);
            $costs = Condition::find($venue->next_condition_id);
            $this->assertSame('¿Los costos logísticos serán asumidos por el cliente?', $costs->label);
            $this->assertSame($schedule->id, $costs->next_condition_id);

            $which = Condition::find($costs->options()->where('label', 'Sí')->value('next_condition_id'));
            $this->assertSame('¿Cuáles costos logísticos asumirá el cliente?', $which->label);
            $this->assertSame($schedule->id, $which->next_condition_id);
            $this->assertNull($costs->options()->where('label', 'No')->value('next_condition_id'));
        }
    }

    public function test_budget_and_liaison_meeting_ask_for_details_only_when_they_apply(): void
    {
        $budget = $this->question('consultoria', 'Presupuesto establecido para la prestación del servicio');
        $this->assertSame(['Definido', 'Por definir', 'No disponible'], $budget->options()->orderBy('id')->pluck('label')->all());
        $this->assertSame('Valor del presupuesto establecido', Condition::find($budget->options()->where('label', 'Definido')->value('next_condition_id'))->label);

        $meeting = $this->question('consultoria', 'En caso de precisar las necesidades del servicio, ¿estaría de acuerdo en realizar una reunión de enlace?');
        $this->assertSame('Disponibilidad para la reunión de enlace', Condition::find($meeting->options()->where('label', 'Sí')->value('next_condition_id'))->label);
        $this->assertNull($meeting->options()->where('label', 'No')->value('next_condition_id'));
    }

    public function test_key_fields_are_required_and_the_arl_and_documents_optional(): void
    {
        $required = [
            'Descripción del servicio a solicitar',
            'Descripción del alcance actual del sistema de gestión',
            'Descripción del nombre de procesos y/o macroprocesos',
            'Alcance certificado',
            'Perfil del equipo requerido para la prestación del servicio',
            'Modalidad de prestación del servicio',
            'Fecha estimada para la prestación del servicio',
            'Fecha estimada de entrega de la oferta comercial',
        ];
        foreach (['auditoria', 'consultoria'] as $name) {
            foreach ($required as $label) {
                $this->assertTrue($this->question($name, $label)->isRequired(), "{$name}: {$label}");
            }
            foreach (['Nivel de riesgo de la empresa ante la ARL', 'Documentos o información a tener en cuenta para la cotización'] as $label) {
                $this->assertFalse($this->question($name, $label)->isRequired(), "{$name}: {$label}");
            }
        }

        $last = $this->question('formacion', 'Fecha estimada de entrega de la oferta comercial');
        $this->assertNull($last->next_condition_id, 'the offer deadline closes the request');
        $this->assertSame('cierre', $last->getSection());
    }

    private function unit(string $name): BusinessUnit
    {
        return BusinessUnit::where('name', $name)->sole();
    }

    /** @return list<string> */
    private function labels(string $unit): array
    {
        return $this->unit($unit)->conditions()->pluck('label')->all();
    }

    private function question(string $unit, string $label): Condition
    {
        return $this->unit($unit)->conditions()->where('label', $label)->sole();
    }
}
