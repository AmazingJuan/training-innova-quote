<?php

namespace App\Http\Requests\Admin;

use App\Models\Condition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One question of the decision tree, with its options. Placement fields only
 * apply when creating. Links (next_condition) are checked against the whole
 * tree by DecisionTreeService before anything is committed.
 */
class DecisionTreeQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isChoice = $this->input('interaction_type') === 'options';

        return [
            'label' => ['required', 'string', 'max:255'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'placeholder' => ['nullable', 'string', 'max:500'],
            'multiline' => ['boolean'],
            'interaction_type' => ['required', Rule::in(['input', 'options', 'range'])],
            // A range is of numbers or dates; only an open field can take files.
            'type' => ['required', Rule::in($this->input('interaction_type') === 'range' ? ['number', 'date'] : ['text', 'number', 'date', 'file'])],
            'multiple' => ['boolean'],
            'section' => ['nullable', Rule::in(Condition::SECTIONS)],
            'required' => ['boolean'],
            'next_condition' => ['nullable', 'integer'],

            'options' => [Rule::requiredIf($isChoice), 'array', $isChoice ? 'min:1' : 'max:0'],
            'options.*.id' => ['nullable', 'integer', 'distinct'],
            'options.*.label' => ['required', 'string', 'max:255'],
            'options.*.value' => ['nullable', 'string', 'max:255'],
            'options.*.is_other' => ['boolean'],
            'options.*.next_condition' => ['nullable', 'integer'],

            'branch_option_id' => ['nullable', 'integer'],
            'at_start' => ['boolean'],
            'insert_after' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Escriba la pregunta.',
            'options.required' => 'Agregue al menos una opción.',
            'options.min' => 'Agregue al menos una opción.',
            'options.max' => 'Este tipo de respuesta no lleva opciones.',
            'options.*.label.required' => 'Hay opciones sin texto: escríbalas o quítelas.',
        ];
    }

    /**
     * @return array{branch_option_id: int|null, at_start: bool, insert_after: int|null}
     */
    public function placement(): array
    {
        return [
            'branch_option_id' => $this->filled('branch_option_id') ? $this->integer('branch_option_id') : null,
            'at_start' => $this->boolean('at_start'),
            'insert_after' => $this->filled('insert_after') ? $this->integer('insert_after') : null,
        ];
    }
}
