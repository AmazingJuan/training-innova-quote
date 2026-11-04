<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBusinessUnitRequest;
use App\Models\BusinessUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminBusinessUnitController extends Controller
{
    private const PUBLIC_COLUMNS = ['id', 'display_name', 'abbreviation', 'quotation_seq_year', 'quotation_seq_value'];

    public function index(Request $request): InertiaResponse
    {
        $search = $request->string('search')->trim()->toString();

        $businessUnits = BusinessUnit::query()
            ->select(self::PUBLIC_COLUMNS)
            ->search($search, ['display_name', 'abbreviation'])
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/business-units/index', [
            'viewData' => [
                'businessUnits' => $businessUnits,
                'filters' => ['search' => $search],
            ],
        ]);
    }

    public function show(BusinessUnit $businessUnit): InertiaResponse
    {
        return Inertia::render('admin/business-units/show', [
            'viewData' => [
                'businessUnit' => $businessUnit->only(self::PUBLIC_COLUMNS),
                'services' => $businessUnit->services()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('admin/business-units/create');
    }

    public function store(AdminBusinessUnitRequest $request): RedirectResponse
    {
        $businessUnit = BusinessUnit::create($request->validated());

        return to_route('dashboard.business-unit.index')
            ->with('success', 'La unidad de negocio «'.$businessUnit->getDisplayName().'» ha sido creada exitosamente.');
    }

    public function edit(BusinessUnit $businessUnit): InertiaResponse
    {
        return Inertia::render('admin/business-units/edit', [
            'viewData' => [
                'businessUnit' => $businessUnit->only(self::PUBLIC_COLUMNS),
            ],
        ]);
    }

    public function update(AdminBusinessUnitRequest $request, BusinessUnit $businessUnit): RedirectResponse
    {
        $businessUnit->update($request->validated());

        return to_route('dashboard.business-unit.index')
            ->with('success', 'La unidad de negocio «'.$businessUnit->getDisplayName().'» ha sido actualizada exitosamente.');
    }

    public function destroy(BusinessUnit $businessUnit): RedirectResponse
    {
        if ($businessUnit->services()->exists()) {
            return to_route('dashboard.business-unit.index')
                ->with('error', 'No se puede eliminar la unidad de negocio «'.$businessUnit->getDisplayName().'» porque tiene servicios asociados.');
        }

        $businessUnit->delete();

        return to_route('dashboard.business-unit.index')
            ->with('success', 'La unidad de negocio «'.$businessUnit->getDisplayName().'» ha sido eliminada exitosamente.');
    }
}
