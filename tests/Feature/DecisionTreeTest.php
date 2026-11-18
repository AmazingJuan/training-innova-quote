<?php

namespace Tests\Feature;

use App\Enums\Section;
use App\Models\BusinessUnit;
use App\Models\Condition;
use App\Models\ConditionOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DecisionTreeTest extends TestCase
{
    use RefreshDatabase;

    private BusinessUnit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->unit = BusinessUnit::create(['name' => 'auditoria', 'display_name' => 'Auditoría', 'abbreviation' => 'AUD']);
    }

    public function test_guests_cannot_edit(): void
    {
        auth()->logout();

        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('A'))->assertRedirect(route('login'));
        $this->assertSame(0, Condition::count());
    }

    public function test_editor_lists_every_question_with_option_ids(): void
    {
        [$a] = $this->sequenceOf('A', 'B');
        Condition::create(['label' => 'Suelta', 'interaction_type' => 'input', 'type' => 'text', 'business_unit_id' => $this->unit->id]);
        $option = ConditionOption::create(['label' => 'Sí', 'condition_id' => $a->id]);
        $a->update(['interaction_type' => 'options']);

        $this->get(route('dashboard.business-unit.decision-tree'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('viewData.businessUnits.0.conditions', 3)
                ->where('viewData.businessUnits.0.conditions.0.options.0.id', $option->id));
    }

    public function test_questions_are_created_at_the_end_at_the_start_and_after_another(): void
    {
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('B'))->assertSessionHasNoErrors();
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('D'))->assertSessionHasNoErrors();
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('A', ['at_start' => true]))->assertSessionHasNoErrors();
        $b = Condition::where('label', 'B')->sole();
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('C', ['insert_after' => $b->id]))->assertSessionHasNoErrors();

        $this->assertSame(['A', 'B', 'C', 'D'], $this->order());
    }

    public function test_a_branch_question_is_reached_by_one_option_and_rejoins_the_flow(): void
    {
        [$a, $b] = $this->sequenceOf('A', 'B');
        $a->update(['interaction_type' => 'options']);
        $yes = ConditionOption::create(['label' => 'Sí', 'condition_id' => $a->id]);

        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('Rama', ['branch_option_id' => $yes->id]))->assertSessionHasNoErrors();

        $branch = Condition::where('label', 'Rama')->sole();
        $this->assertSame($branch->id, $yes->fresh()->next_condition_id);
        $this->assertSame($b->id, $branch->next_condition_id);
        $this->assertSame(['A', 'B'], $this->order(), 'the main order is untouched');
    }

    public function test_updating_keeps_option_ids_and_values_and_removes_the_missing_ones(): void
    {
        [$a] = $this->sequenceOf('A');
        $a->update(['interaction_type' => 'options']);
        $keep = ConditionOption::create(['label' => 'Sí', 'value' => 'Y', 'condition_id' => $a->id]);
        $drop = ConditionOption::create(['label' => 'No', 'condition_id' => $a->id]);

        $this->put(route('dashboard.decision-tree.questions.update', $a), $this->input('A editada', [
            'interaction_type' => 'options',
            'options' => [
                ['id' => $keep->id, 'label' => 'Sí, claro', 'value' => 'Y', 'is_other' => false, 'next_condition' => null],
                ['label' => 'Tal vez', 'value' => null, 'is_other' => false, 'next_condition' => null],
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('A editada', $a->fresh()->label);
        $this->assertSame('Sí, claro', $keep->fresh()->label);
        $this->assertSame('Y', $keep->fresh()->value);
        $this->assertModelMissing($drop);
        $this->assertSame(2, $a->options()->count());
    }

    public function test_options_of_another_question_cannot_be_hijacked(): void
    {
        [$a, $b] = $this->sequenceOf('A', 'B');
        $foreign = ConditionOption::create(['label' => 'Ajena', 'condition_id' => $b->id]);

        $this->put(route('dashboard.decision-tree.questions.update', $a), $this->input('A', [
            'interaction_type' => 'options',
            'options' => [['id' => $foreign->id, 'label' => 'Mía', 'is_other' => false]],
        ]))->assertSessionHasErrors('options');

        $this->assertSame('Ajena', $foreign->fresh()->label);
    }

    public function test_the_next_of_a_main_question_is_owned_by_the_order(): void
    {
        [$a, $b, $c] = $this->sequenceOf('A', 'B', 'C');

        $this->put(route('dashboard.decision-tree.questions.update', $a), $this->input('A', ['next_condition' => $c->id]))->assertSessionHasNoErrors();

        $this->assertSame(['A', 'B', 'C'], $this->order());
    }

    public function test_a_change_that_would_create_a_cycle_is_rejected_and_nothing_changes(): void
    {
        [$a, $b] = $this->sequenceOf('A', 'B');
        $a->update(['interaction_type' => 'options']);
        $option = ConditionOption::create(['label' => 'Sí', 'condition_id' => $a->id]);
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('Rama', ['branch_option_id' => $option->id]));
        $branch = Condition::where('label', 'Rama')->sole();

        // Branch → A would loop forever (A → Sí → Rama → A).
        $this->put(route('dashboard.decision-tree.questions.update', $branch), $this->input('Rama cambiada', ['next_condition' => $a->id]))
            ->assertSessionHasErrors('tree');

        $this->assertSame('Rama', $branch->fresh()->label);
        $this->assertSame($b->id, $branch->fresh()->next_condition_id);
    }

    public function test_links_to_questions_of_another_unit_are_rejected(): void
    {
        [$a] = $this->sequenceOf('A');
        $other = BusinessUnit::create(['name' => 'otra', 'display_name' => 'Otra', 'abbreviation' => 'OT']);
        $foreign = Condition::create(['label' => 'Ajena', 'interaction_type' => 'input', 'type' => 'text', 'business_unit_id' => $other->id]);

        $this->put(route('dashboard.decision-tree.questions.update', $a), $this->input('A', [
            'interaction_type' => 'options',
            'options' => [['label' => 'Sí', 'is_other' => false, 'next_condition' => $foreign->id]],
        ]))->assertSessionHasErrors('tree');

        $this->assertSame(0, $a->options()->count());
    }

    public function test_deleting_keeps_the_flow_continuous(): void
    {
        [$a, $b, $c] = $this->sequenceOf('A', 'B', 'C');
        $a->update(['interaction_type' => 'options']);
        $option = ConditionOption::create(['label' => 'Sí', 'condition_id' => $a->id, 'next_condition_id' => $c->id]);

        $this->delete(route('dashboard.decision-tree.questions.delete', $b))->assertSessionHasNoErrors();
        $this->assertSame(['A', 'C'], $this->order());

        $this->delete(route('dashboard.decision-tree.questions.delete', $c))->assertSessionHasNoErrors();
        $this->assertSame(['A'], $this->order());
        $this->assertNull($option->fresh()->next_condition_id, 'the option falls back to the default');

        $this->delete(route('dashboard.decision-tree.questions.delete', $a))->assertSessionHasNoErrors();
        $this->assertNull($this->unit->fresh()->initial_condition_id);
    }

    public function test_questions_move_up_and_down_in_the_order(): void
    {
        [, $b, $c] = $this->sequenceOf('A', 'B', 'C');

        $this->post(route('dashboard.decision-tree.questions.move', $c), ['direction' => 'up']);
        $this->assertSame(['A', 'C', 'B'], $this->order());

        $this->post(route('dashboard.decision-tree.questions.move', $b), ['direction' => 'down']);
        $this->assertSame(['A', 'C', 'B'], $this->order(), 'the last one cannot go further down');

        $this->post(route('dashboard.decision-tree.questions.move', $c), ['direction' => 'up']);
        $this->assertSame(['C', 'A', 'B'], $this->order());
    }

    public function test_questions_can_be_copied_only_into_an_empty_unit(): void
    {
        [$a] = $this->sequenceOf('A', 'B');
        $a->update(['interaction_type' => 'options']);
        ConditionOption::create(['label' => 'Sí', 'condition_id' => $a->id]);
        $empty = BusinessUnit::create(['name' => 'nueva', 'display_name' => 'Nueva', 'abbreviation' => 'NU']);

        $this->post(route('dashboard.decision-tree.copy', $empty), ['source_id' => $this->unit->id])->assertSessionHasNoErrors();
        $this->assertSame(['A', 'B'], $this->order($empty));
        $this->assertSame(1, ConditionOption::whereHas('condition', fn ($q) => $q->where('business_unit_id', $empty->id))->count());

        $this->post(route('dashboard.decision-tree.copy', $empty), ['source_id' => $this->unit->id])->assertSessionHasErrors('tree');
    }

    public function test_block_and_optional_flag_are_saved_and_listed(): void
    {
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('ARL', [
            'section' => 'necesidad',
            'required' => false,
        ]))->assertSessionHasNoErrors();

        $question = Condition::where('label', 'ARL')->sole();
        $this->assertSame(Section::Necesidad, $question->getSection());
        $this->assertFalse($question->isRequired());

        $this->get(route('dashboard.business-unit.decision-tree'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('viewData.businessUnits.0.conditions.0.section', 'necesidad')
                ->where('viewData.businessUnits.0.conditions.0.required', false));
    }

    public function test_open_questions_keep_their_example_and_long_field(): void
    {
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('Descripción', [
            'placeholder' => 'Ej.: auditoría interna ISO 9001',
            'multiline' => true,
        ]))->assertSessionHasNoErrors();
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('Modalidad', [
            'interaction_type' => 'options',
            'placeholder' => 'No aplica',
            'multiline' => true,
            'options' => [['label' => 'Presencial', 'is_other' => false]],
        ]))->assertSessionHasNoErrors();

        $open = Condition::where('label', 'Descripción')->sole();
        $this->assertSame('Ej.: auditoría interna ISO 9001', $open->getPlaceholder());
        $this->assertTrue($open->isMultiline());

        $choice = Condition::where('label', 'Modalidad')->sole();
        $this->assertNull($choice->getPlaceholder(), 'choices have no example inside a field');
        $this->assertFalse($choice->isMultiline());
    }

    public function test_unknown_blocks_are_rejected(): void
    {
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('A', ['section' => 'otro']))
            ->assertSessionHasErrors('section');

        $this->assertSame(0, Condition::count());
    }

    public function test_copying_keeps_block_and_optional_flag(): void
    {
        [$a] = $this->sequenceOf('A');
        $a->update(['section' => 'cierre', 'required' => false]);
        $empty = BusinessUnit::create(['name' => 'nueva', 'display_name' => 'Nueva', 'abbreviation' => 'NU']);

        $this->post(route('dashboard.decision-tree.copy', $empty), ['source_id' => $this->unit->id])->assertSessionHasNoErrors();

        $copy = $empty->conditions()->sole();
        $this->assertSame(Section::Cierre, $copy->getSection());
        $this->assertFalse($copy->isRequired());
    }

    public function test_choice_questions_need_options_with_text(): void
    {
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('A', ['interaction_type' => 'options', 'options' => []]))
            ->assertSessionHasErrors('options');
        $this->post(route('dashboard.decision-tree.questions.store', $this->unit), $this->input('A', ['interaction_type' => 'options', 'options' => [['label' => '', 'is_other' => false]]]))
            ->assertSessionHasErrors('options.0.label');

        $this->assertSame(0, Condition::count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function input(string $label, array $overrides = []): array
    {
        return [
            'label' => $label,
            'observation' => null,
            'interaction_type' => 'input',
            'type' => 'text',
            'multiple' => false,
            'options' => [],
            ...$overrides,
        ];
    }

    /**
     * Create a chain of plain questions as the unit's main order.
     *
     * @return list<Condition>
     */
    private function sequenceOf(string ...$labels): array
    {
        $questions = array_map(
            fn ($label) => Condition::create(['label' => $label, 'interaction_type' => 'input', 'type' => 'text', 'business_unit_id' => $this->unit->id]),
            $labels,
        );

        foreach ($questions as $i => $question) {
            $question->update(['next_condition_id' => ($questions[$i + 1] ?? null)?->id]);
        }
        $this->unit->update(['initial_condition_id' => $questions[0]->id]);

        return $questions;
    }

    /** Labels of the main order, following the defaults from the first question. */
    private function order(?BusinessUnit $unit = null): array
    {
        $unit = ($unit ?? $this->unit)->fresh();
        $labels = [];
        $question = Condition::find($unit->initial_condition_id);
        while ($question && count($labels) < 50) {
            $labels[] = $question->label;
            $question = Condition::find($question->next_condition_id);
        }

        return $labels;
    }
}
