<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecisionTreeQuestionRequest;
use App\Models\BusinessUnit;
use App\Models\Condition;
use App\Services\DecisionTreeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Editor of the quotation questionnaire. Each action is a small, validated
 * operation on one question; the page reloads its data after each one.
 */
class AdminDecisionTreeController extends Controller
{
    public function __construct(private readonly DecisionTreeService $trees) {}

    public function index(): InertiaResponse
    {
        $businessUnits = BusinessUnit::query()
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'initial_condition_id'])
            ->map(fn (BusinessUnit $unit) => [
                'id' => $unit->getId(),
                'name' => $unit->getDisplayName(),
                'initial_condition_id' => $unit->initial_condition_id,
                'conditions' => $this->trees->allConditions($unit),
            ]);

        return Inertia::render('admin/decision-tree/index', [
            'viewData' => ['businessUnits' => $businessUnits],
        ]);
    }

    public function store(DecisionTreeQuestionRequest $request, BusinessUnit $businessUnit): RedirectResponse
    {
        $question = $this->trees->createQuestion($businessUnit, $request->validated(), $request->placement());

        return back()->with('success', 'Pregunta «'.$question->getLabel().'» creada.');
    }

    public function update(DecisionTreeQuestionRequest $request, Condition $condition): RedirectResponse
    {
        $this->trees->updateQuestion($condition, $request->validated());

        return back()->with('success', 'Pregunta «'.$condition->getLabel().'» guardada.');
    }

    public function destroy(Condition $condition): RedirectResponse
    {
        $this->trees->deleteQuestion($condition);

        return back()->with('success', 'Pregunta «'.$condition->getLabel().'» eliminada.');
    }

    public function move(Request $request, Condition $condition): RedirectResponse
    {
        $this->trees->moveQuestion($condition, $request->validate(['direction' => ['required', 'in:up,down']])['direction'] === 'up' ? -1 : 1);

        return back();
    }

    public function copy(Request $request, BusinessUnit $businessUnit): RedirectResponse
    {
        $source = BusinessUnit::query()->findOrFail($request->validate(['source_id' => ['required', 'integer']])['source_id']);
        $this->trees->copyTree($businessUnit, $source);

        return back()->with('success', 'Preguntas copiadas desde «'.$source->getDisplayName().'».');
    }
}
