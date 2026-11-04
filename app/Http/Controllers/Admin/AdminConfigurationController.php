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

    private function configuration(): Configuration
    {
        $config = Configuration::current();
        abort_if($config === null, 500, 'No existe el registro de configuración. Ejecute las migraciones (php artisan migrate).');

        return $config;
    }
}
