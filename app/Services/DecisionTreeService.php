<?php

namespace App\Services;

use App\Models\BusinessUnit;
use App\Models\Condition;
use App\Models\ConditionOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reads and edits the per-business-unit decision tree (questions + options).
 *
 * The tree has a "main sequence": the first question (the unit's initial
 * condition) followed by each question's default `next_condition`. Questions
 * outside it are branches, reached through an option. Every edit is a small
 * operation that runs in its own transaction, holds a lock on the unit and
 * re-validates the whole tree before committing: if the result would be
 * broken (a cycle, a link to a missing question…) nothing is written.
 *
 * Wire format of a question:
 *   { id, label, interaction_type, type, observation, placeholder, multiline,
 *     multiple, section, required, next_condition,
 *     options: [ { id, label, value, is_other, next_condition } ] }
 *
 * `section` is the block of the "formato de encuadre" the question belongs
 * to; a question that is not `required` can be left blank.
 */
class DecisionTreeService
{
    /**
     * Questions reachable from the first one, keyed by id, in BFS order.
     * This is what the public quotation flow walks through.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reachableTree(BusinessUnit $businessUnit): array
    {
        $byId = $this->loadConditions($businessUnit)->keyBy('id');
        $queue = [$businessUnit->initial_condition_id];
        $tree = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            $condition = $id !== null ? $byId->get($id) : null;
            if ($condition === null || isset($tree[$id])) {
                continue;
            }

            $tree[$id] = $this->serialize($condition);

            $queue[] = $condition->next_condition_id;
            foreach ($condition->options as $option) {
                $queue[] = $option->next_condition_id;
            }
        }

        return $tree;
    }

    /**
     * Every question of the unit (including unlinked ones), for the editor.
     *
     * @return list<array<string, mixed>>
     */
    public function allConditions(BusinessUnit $businessUnit): array
    {
        return $this->loadConditions($businessUnit)
            ->map(fn (Condition $condition) => ['id' => $condition->getId(), ...$this->serialize($condition)])
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Granular edits
    |--------------------------------------------------------------------------
    */

    /**
     * Create a question. Placement, in order of precedence:
     *  - branch_option_id: as a branch reached by that option; afterwards the
     *    client continues where the option's question would have gone;
     *  - at_start: first question of the main sequence;
     *  - insert_after: right after that question of the main sequence;
     *  - otherwise: at the end of the main sequence.
     *
     * @param  array<string, mixed>  $data
     * @param  array{branch_option_id?: int|null, at_start?: bool, insert_after?: int|null}  $placement
     */
    public function createQuestion(BusinessUnit $businessUnit, array $data, array $placement): Condition
    {
        return $this->mutate($businessUnit, function (BusinessUnit $unit) use ($data, $placement) {
            $question = $unit->conditions()->create($this->fields($data));
            $this->syncOptions($question, $data);

            if ($optionId = $placement['branch_option_id'] ?? null) {
                $option = $this->optionOf($unit, $optionId);
                $question->update(['next_condition_id' => $option->condition->next_condition_id]);
                $option->update(['next_condition_id' => $question->id]);

                return $question;
            }

            $sequence = $this->sequence($unit);
            $at = match (true) {
                (bool) ($placement['at_start'] ?? false) => 0,
                isset($placement['insert_after']) => $this->positionIn($sequence, $placement['insert_after']) + 1,
                default => count($sequence),
            };
            array_splice($sequence, $at, 0, [$question->id]);
            $this->writeSequence($unit, $sequence);

            return $question;
        });
    }

    /**
     * Update a question and its options. Options keep their ids; the ones not
     * sent are removed. The default next of a main-sequence question is owned
     * by the order (moveQuestion) and is not editable here.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateQuestion(Condition $question, array $data): Condition
    {
        return $this->mutate($question->businessUnit, function (BusinessUnit $unit) use ($question, $data) {
            $fields = $this->fields($data);

            if (array_key_exists('next_condition', $data) && ! in_array($question->id, $this->sequence($unit), true)) {
                $fields['next_condition_id'] = $data['next_condition'];
            }

            $question->update($fields);
            $this->syncOptions($question, $data);

            return $question;
        });
    }

    /**
     * Delete a question, keeping the flow continuous: whatever led to it now
     * continues where it went, and options pointing to it fall back to their
     * question's default.
     */
    public function deleteQuestion(Condition $question): void
    {
        $this->mutate($question->businessUnit, function (BusinessUnit $unit) use ($question) {
            $next = $question->next_condition_id;
            $ids = $unit->conditions()->pluck('id');

            Condition::query()->whereIn('id', $ids)->where('next_condition_id', $question->id)->update(['next_condition_id' => $next]);
            ConditionOption::query()->whereIn('condition_id', $ids)->where('next_condition_id', $question->id)->update(['next_condition_id' => null]);

            if ((int) $unit->initial_condition_id === $question->id) {
                $unit->update(['initial_condition_id' => $next]);
            }

            $question->delete();
        });
    }

    /** Move a main-sequence question one step up (-1) or down (+1). */
    public function moveQuestion(Condition $question, int $delta): void
    {
        $this->mutate($question->businessUnit, function (BusinessUnit $unit) use ($question, $delta) {
            $sequence = $this->sequence($unit);
            $from = $this->positionIn($sequence, $question->id);
            $to = $from + $delta;

            if ($to < 0 || $to >= count($sequence)) {
                return;
            }

            [$sequence[$from], $sequence[$to]] = [$sequence[$to], $sequence[$from]];
            $this->writeSequence($unit, $sequence);
        });
    }

    /** Copy another unit's whole questionnaire into an empty unit. */
    public function copyTree(BusinessUnit $target, BusinessUnit $source): void
    {
        $this->mutate($target, function (BusinessUnit $unit) use ($source) {
            if ($unit->conditions()->exists()) {
                throw ValidationException::withMessages(['tree' => 'Solo se pueden copiar preguntas a una unidad que aún no tiene.']);
            }

            $questions = $this->loadConditions($source);
            $ids = [];
            foreach ($questions as $question) {
                $ids[$question->id] = $unit->conditions()->create(
                    $question->only('label', 'interaction_type', 'type', 'observation', 'placeholder', 'multiline', 'multiple', 'section', 'required'),
                )->id;
            }

            $map = fn (?int $id) => $id === null ? null : $ids[$id] ?? null;
            foreach ($questions as $question) {
                Condition::query()->whereKey($ids[$question->id])->update(['next_condition_id' => $map($question->next_condition_id)]);
                foreach ($question->options as $option) {
                    ConditionOption::create([
                        ...$option->only('label', 'value', 'is_other'),
                        'condition_id' => $ids[$question->id],
                        'next_condition_id' => $map($option->next_condition_id),
                    ]);
                }
            }

            $unit->update(['initial_condition_id' => $map($source->initial_condition_id)]);
        });
    }

    /**
     * Behaviour-preserving cleanup: option targets equal to the question's
     * default are dropped, and when every option of a single-choice question
     * leads to the same place, that place becomes the default. Multi-choice
     * questions never branch at runtime, so their option targets are cleared.
     */
    public function normalizeBranches(BusinessUnit $businessUnit): void
    {
        DB::transaction(function () use ($businessUnit) {
            foreach ($this->loadConditions($businessUnit) as $question) {
                if ($question->interaction_type !== 'options' || $question->options->isEmpty()) {
                    continue;
                }

                $default = $question->next_condition_id;
                if (! $question->multiple) {
                    $targets = $question->options->map(fn (ConditionOption $o) => $o->next_condition_id ?? $default)->unique();
                    if ($targets->count() === 1) {
                        $default = $targets->first();
                        $question->update(['next_condition_id' => $default]);
                    }
                }

                ConditionOption::query()
                    ->where('condition_id', $question->id)
                    ->when(! $question->multiple, fn ($q) => $default === null ? $q->whereRaw('1 = 0') : $q->where('next_condition_id', $default))
                    ->update(['next_condition_id' => null]);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Structural problems of a tree in wire format (dangling links, cycles).
     *
     * @param  list<array<string, mixed>>  $conditions
     * @return list<string>
     */
    public function structuralErrors(array $conditions, int|string|null $initialId): array
    {
        $labels = [];
        $edges = [];
        foreach ($conditions as $condition) {
            $labels[$condition['id']] = trim((string) ($condition['label'] ?? '')) ?: 'Pregunta #'.$condition['id'];
            $edges[$condition['id']] = array_values(array_filter(
                [$condition['next_condition'] ?? null, ...array_column($condition['options'] ?? [], 'next_condition')],
                fn ($target) => $target !== null,
            ));
        }

        $errors = [];

        if ($initialId === null && $conditions !== []) {
            $errors[] = 'El cuestionario debe tener una primera pregunta.';
        }
        if ($initialId !== null && ! isset($labels[$initialId])) {
            $errors[] = 'La primera pregunta no pertenece a esta unidad.';
        }

        foreach ($edges as $from => $targets) {
            foreach ($targets as $target) {
                if (! isset($labels[$target])) {
                    $errors[] = "«{$labels[$from]}» lleva a una pregunta que no existe en esta unidad.";
                }
            }
        }

        if ($cycle = $this->findCycle($edges)) {
            $errors[] = 'Ese cambio crearía un ciclo: '.implode(' → ', array_map(fn ($id) => "«{$labels[$id]}»", $cycle)).'. El cliente nunca terminaría.';
        }

        return $errors;
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * Run an edit atomically: lock the unit, apply, re-validate the whole tree
     * and roll back (with a validation error) if the result would be broken.
     *
     * @template T
     *
     * @param  callable(BusinessUnit): T  $edit
     * @return T
     */
    private function mutate(BusinessUnit $businessUnit, callable $edit): mixed
    {
        return DB::transaction(function () use ($businessUnit, $edit) {
            $unit = BusinessUnit::query()->whereKey($businessUnit->id)->lockForUpdate()->firstOrFail();

            $result = $edit($unit);

            $unit->refresh();
            $errors = $this->structuralErrors($this->allConditions($unit), $unit->initial_condition_id);
            if ($errors !== []) {
                throw ValidationException::withMessages(['tree' => $errors]);
            }

            return $result;
        });
    }

    /**
     * Ids of the main sequence, from the first question along each default next.
     *
     * @return list<int>
     */
    private function sequence(BusinessUnit $unit): array
    {
        $next = $unit->conditions()->pluck('next_condition_id', 'id');
        $sequence = [];
        $id = $unit->initial_condition_id;

        while ($id !== null && $next->has($id) && ! in_array($id, $sequence, true)) {
            $sequence[] = $id;
            $id = $next[$id];
        }

        return $sequence;
    }

    /** Point each question of `sequence` to the following one and make the first the start. */
    private function writeSequence(BusinessUnit $unit, array $sequence): void
    {
        foreach ($sequence as $i => $id) {
            Condition::query()->whereKey($id)->update(['next_condition_id' => $sequence[$i + 1] ?? null]);
        }

        $unit->update(['initial_condition_id' => $sequence[0] ?? null]);
    }

    /** @param  list<int>  $sequence */
    private function positionIn(array $sequence, int $id): int
    {
        $position = array_search($id, array_map('intval', $sequence), true);

        if ($position === false) {
            throw ValidationException::withMessages(['tree' => 'Esa pregunta no está en el orden principal.']);
        }

        return $position;
    }

    private function optionOf(BusinessUnit $unit, int $optionId): ConditionOption
    {
        $option = ConditionOption::query()
            ->whereKey($optionId)
            ->whereHas('condition', fn ($q) => $q->where('business_unit_id', $unit->id))
            ->with('condition')
            ->first();

        if ($option === null) {
            throw ValidationException::withMessages(['tree' => 'La opción indicada no pertenece a esta unidad.']);
        }

        return $option;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fields(array $data): array
    {
        $isChoice = $data['interaction_type'] === 'options';

        return [
            'label' => trim($data['label']),
            'interaction_type' => $data['interaction_type'],
            'type' => $isChoice ? 'text' : $data['type'],
            'observation' => trim((string) ($data['observation'] ?? '')) ?: null,
            'placeholder' => $isChoice ? null : (trim((string) ($data['placeholder'] ?? '')) ?: null),
            'multiline' => ! $isChoice && $data['interaction_type'] === 'input' && $data['type'] === 'text' && (bool) ($data['multiline'] ?? false),
            'multiple' => $isChoice && (bool) ($data['multiple'] ?? false),
            'section' => $data['section'] ?? null,
            'required' => (bool) ($data['required'] ?? true),
        ];
    }

    /**
     * Upsert the options by id and delete the ones not sent. Non-choice
     * questions end up with no options.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncOptions(Condition $question, array $data): void
    {
        $incoming = $data['interaction_type'] === 'options' ? ($data['options'] ?? []) : [];
        $existing = $question->options()->get()->keyBy('id');
        $keep = [];

        foreach ($incoming as $option) {
            $attributes = [
                'label' => trim($option['label']),
                'value' => $option['value'] ?? null,
                'is_other' => (bool) ($option['is_other'] ?? false),
                // Multi-choice questions never branch per option.
                'next_condition_id' => ($data['multiple'] ?? false) ? null : ($option['next_condition'] ?? null),
            ];

            if ($id = $option['id'] ?? null) {
                $model = $existing->get($id) ?? throw ValidationException::withMessages(['options' => 'Una de las opciones no pertenece a esta pregunta.']);
                $model->update($attributes);
                $keep[] = $id;
            } else {
                $keep[] = $question->options()->create($attributes)->id;
            }
        }

        $question->options()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @return Collection<int, Condition>
     */
    private function loadConditions(BusinessUnit $businessUnit): Collection
    {
        return $businessUnit->conditions()->with('options')->orderBy('id')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Condition $condition): array
    {
        return [
            'label' => $condition->getLabel(),
            'interaction_type' => $condition->getInteractionType(),
            'type' => $condition->getType(),
            'observation' => $condition->getObservation(),
            'placeholder' => $condition->getPlaceholder(),
            'multiline' => $condition->isMultiline(),
            'multiple' => $condition->getMultiple(),
            'section' => $condition->getSection(),
            'required' => $condition->isRequired(),
            'next_condition' => $condition->next_condition_id,
            'options' => $condition->options
                ->sortBy('id')
                ->map(fn (ConditionOption $option) => [
                    'id' => $option->getId(),
                    'label' => $option->getLabel(),
                    'value' => $option->getValue(),
                    'is_other' => $option->isOther(),
                    'next_condition' => $option->getNextConditionId(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * Depth-first search for a cycle; returns the closed path or null.
     *
     * @param  array<int|string, list<int|string>>  $edges
     * @return list<int|string>|null
     */
    private function findCycle(array $edges): ?array
    {
        $state = [];   // 1 = on the current path, 2 = fully explored
        $path = [];

        $visit = function ($node) use (&$visit, &$state, &$path, $edges): ?array {
            $state[$node] = 1;
            $path[] = $node;

            foreach ($edges[$node] ?? [] as $next) {
                if (($state[$next] ?? 0) === 1) {
                    return [...array_slice($path, array_search($next, $path, true)), $next];
                }
                if (! isset($state[$next]) && isset($edges[$next]) && ($cycle = $visit($next))) {
                    return $cycle;
                }
            }

            array_pop($path);
            $state[$node] = 2;

            return null;
        };

        foreach (array_keys($edges) as $node) {
            if (! isset($state[$node]) && ($cycle = $visit($node))) {
                return $cycle;
            }
        }

        return null;
    }
}
