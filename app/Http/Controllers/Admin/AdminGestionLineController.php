<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminGestionLineRequest;
use App\Models\GestionLine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminGestionLineController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = $request->string('search')->trim()->toString();

        $gestionLines = GestionLine::query()
            ->select('id', 'name')
            ->search($search, ['name'])
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/gestion-lines/index', [
            'viewData' => [
                'gestionLines' => $gestionLines,
                'filters' => ['search' => $search],
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('admin/gestion-lines/create');
    }

    public function store(AdminGestionLineRequest $request): RedirectResponse
    {
        GestionLine::create($request->validated());

        return to_route('dashboard.lines.index')->with('success', 'Línea de gestión creada correctamente.');
    }

    public function edit(GestionLine $gestionLine): InertiaResponse
    {
        return Inertia::render('admin/gestion-lines/edit', [
            'gestionLine' => $gestionLine->only('id', 'name'),
        ]);
    }

    public function update(AdminGestionLineRequest $request, GestionLine $gestionLine): RedirectResponse
    {
        $gestionLine->update($request->validated());

        return to_route('dashboard.lines.index')->with('success', 'Línea de gestión actualizada exitosamente.');
    }

    public function destroy(GestionLine $gestionLine): RedirectResponse
    {
        if ($gestionLine->services()->exists()) {
            return to_route('dashboard.lines.index')->with('error', 'No se puede eliminar la línea de gestión «'.$gestionLine->getName().'» porque tiene servicios asociados.');
        }

        $gestionLine->delete();

        return to_route('dashboard.lines.index')->with('success', 'Línea de gestión eliminada exitosamente.');
    }
}
