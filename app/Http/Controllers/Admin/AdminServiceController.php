<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminServiceRequest;
use App\Models\BusinessUnit;
use App\Models\GestionLine;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminServiceController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = $request->string('search')->trim()->toString();
        $businessUnitId = $request->integer('business_unit_id') ?: null;

        $services = Service::query()
            ->select('id', 'name', 'business_unit_id', 'gestion_line_id')
            ->with(['businessUnit:id,display_name', 'gestionLine:id,name'])
            ->when($businessUnitId, fn ($query) => $query->where('business_unit_id', $businessUnitId))
            ->search($search, ['name'])
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/services/index', [
            'viewData' => [
                'services' => $services,
                'businessUnits' => BusinessUnit::query()->orderBy('display_name')->get(['id', 'display_name']),
                'filters' => [
                    'search' => $search,
                    'business_unit_id' => $businessUnitId ? (string) $businessUnitId : '',
                ],
            ],
        ]);
    }

    public function create(): InertiaResponse|RedirectResponse
    {
        return $this->renderForm('admin/services/create');
    }

    public function store(AdminServiceRequest $request): RedirectResponse
    {
        Service::create($request->validated());

        return to_route('dashboard.services.index')->with('success', 'Servicio creado correctamente.');
    }

    public function edit(Service $service): InertiaResponse|RedirectResponse
    {
        return $this->renderForm('admin/services/edit', [
            'service' => $service->only('id', 'name', 'business_unit_id', 'gestion_line_id'),
        ]);
    }

    public function update(AdminServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        return to_route('dashboard.services.index')->with('success', 'Servicio actualizado exitosamente.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        return to_route('dashboard.services.index')->with('success', 'Servicio eliminado exitosamente.');
    }

    /**
     * A service needs a business unit and a gestion line, so the form is only
     * reachable when both catalogs have at least one entry.
     *
     * @param  array<string, mixed>  $props
     */
    private function renderForm(string $component, array $props = []): InertiaResponse|RedirectResponse
    {
        $businessUnits = BusinessUnit::query()->orderBy('display_name')->get(['id', 'display_name']);
        if ($businessUnits->isEmpty()) {
            return to_route('dashboard.services.index')->with('error', 'No existen unidades de negocio con las cuales usted pueda asociar un servicio.');
        }

        $gestionLines = GestionLine::query()->orderBy('name')->get(['id', 'name']);
        if ($gestionLines->isEmpty()) {
            return to_route('dashboard.services.index')->with('error', 'No existen líneas de gestión con las cuales usted pueda asociar un servicio.');
        }

        return Inertia::render($component, [
            ...$props,
            'businessUnits' => $businessUnits,
            'gestionLines' => $gestionLines,
        ]);
    }
}
