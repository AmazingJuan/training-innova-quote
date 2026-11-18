<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminConfigurationUpdateRequest;
use App\Models\Configuration;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminConfigurationController extends Controller
{
    public function edit(): InertiaResponse
    {
        $config = $this->configuration();

        return Inertia::render('admin/configurations/edit', [
            'viewData' => [
                'configuration' => $config->only($config->getFillable()),
            ],
        ]);
    }

    public function update(AdminConfigurationUpdateRequest $request): RedirectResponse
    {
        $this->configuration()->update($request->validated());

        return to_route('dashboard.configurations.edit')->with('success', 'Configuración actualizada exitosamente.');
    }

    /** The row itself (not the cached copy in Settings): it is about to be edited. */
    private function configuration(): Configuration
    {
        return Configuration::query()->firstOrFail();
    }
}
