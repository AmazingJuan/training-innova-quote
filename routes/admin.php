<?php

use App\Http\Controllers\Admin\AdminBusinessUnitController;
use App\Http\Controllers\Admin\AdminClientController;
use App\Http\Controllers\Admin\AdminConfigurationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDecisionTreeController;
use App\Http\Controllers\Admin\AdminGestionLineController;
use App\Http\Controllers\Admin\AdminQuotationOrderController;
use App\Http\Controllers\Admin\AdminServiceController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWelcomeBroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')
    ->middleware(['auth', 'verified'])
    ->whereNumber(['user', 'service', 'gestionLine', 'client', 'businessUnit', 'condition'])
    ->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Usuarios
        Route::get('/usuarios', [AdminUserController::class, 'index'])->name('dashboard.users.index');
        Route::get('/usuarios/crear', [AdminUserController::class, 'create'])->name('dashboard.users.create');
        Route::post('/usuarios', [AdminUserController::class, 'store'])->name('dashboard.users.store');
        Route::get('/usuarios/{user}/editar', [AdminUserController::class, 'edit'])->name('dashboard.users.edit');
        Route::put('/usuarios/{user}', [AdminUserController::class, 'update'])->name('dashboard.users.update');
        Route::delete('/usuarios/{user}', [AdminUserController::class, 'destroy'])->name('dashboard.users.delete');

        // Servicios
        Route::get('/servicios', [AdminServiceController::class, 'index'])->name('dashboard.services.index');
        Route::get('/servicios/crear', [AdminServiceController::class, 'create'])->name('dashboard.services.create');
        Route::post('/servicios', [AdminServiceController::class, 'store'])->name('dashboard.services.store');
        Route::get('/servicios/{service}/editar', [AdminServiceController::class, 'edit'])->name('dashboard.services.edit');
        Route::put('/servicios/{service}', [AdminServiceController::class, 'update'])->name('dashboard.services.update');
        Route::delete('/servicios/{service}', [AdminServiceController::class, 'destroy'])->name('dashboard.services.delete');

        // Líneas de gestión
        Route::get('/lineas-gestion', [AdminGestionLineController::class, 'index'])->name('dashboard.lines.index');
        Route::get('/lineas-gestion/crear', [AdminGestionLineController::class, 'create'])->name('dashboard.lines.create');
        Route::post('/lineas-gestion', [AdminGestionLineController::class, 'store'])->name('dashboard.lines.store');
        Route::get('/lineas-gestion/{gestionLine}/editar', [AdminGestionLineController::class, 'edit'])->name('dashboard.lines.edit');
        Route::put('/lineas-gestion/{gestionLine}', [AdminGestionLineController::class, 'update'])->name('dashboard.lines.update');
        Route::delete('/lineas-gestion/{gestionLine}', [AdminGestionLineController::class, 'destroy'])->name('dashboard.lines.delete');

        // Clientes
        Route::get('/clientes', [AdminClientController::class, 'index'])->name('dashboard.clients.index');
        Route::post('/clientes/exportar', [AdminClientController::class, 'startExport'])->name('dashboard.clients.export.start');
        Route::get('/clientes/exportar/{token}/estado', [AdminClientController::class, 'exportStatus'])->whereUuid('token')->name('dashboard.clients.export.status');
        Route::get('/clientes/exportar/{token}/descargar', [AdminClientController::class, 'downloadExport'])->whereUuid('token')->name('dashboard.clients.export.download');
        Route::get('/clientes/{client}', [AdminClientController::class, 'show'])->name('dashboard.clients.show');

        // Órdenes de cotización
        Route::get('/ordenes-cotizacion', [AdminQuotationOrderController::class, 'index'])->name('dashboard.quotation-orders.index');
        Route::get('/ordenes-cotizacion/{quotationOrder}', [AdminQuotationOrderController::class, 'show'])->name('dashboard.quotation-orders.show');
        Route::post('/ordenes-cotizacion/{quotationOrder}/upload-quotation-url', [AdminQuotationOrderController::class, 'uploadQuotationUrl'])->name('dashboard.quotation-orders.upload-quotation-url');

        // Unidades de negocio
        Route::get('/unidad-negocio', [AdminBusinessUnitController::class, 'index'])->name('dashboard.business-unit.index');
        Route::get('/unidad-negocio/crear', [AdminBusinessUnitController::class, 'create'])->name('dashboard.business-unit.create');
        Route::post('/unidad-negocio', [AdminBusinessUnitController::class, 'store'])->name('dashboard.business-unit.store');
        Route::get('/unidad-negocio/{businessUnit}', [AdminBusinessUnitController::class, 'show'])->name('dashboard.business-unit.show');
        Route::get('/unidad-negocio/{businessUnit}/editar', [AdminBusinessUnitController::class, 'edit'])->name('dashboard.business-unit.edit');
        Route::put('/unidad-negocio/{businessUnit}', [AdminBusinessUnitController::class, 'update'])->name('dashboard.business-unit.update');
        Route::delete('/unidad-negocio/{businessUnit}', [AdminBusinessUnitController::class, 'destroy'])->name('dashboard.business-unit.delete');

        // Árbol de decisión
        Route::get('/arbol-decision', [AdminDecisionTreeController::class, 'index'])->name('dashboard.business-unit.decision-tree');
        Route::post('/arbol-decision/{businessUnit}/preguntas', [AdminDecisionTreeController::class, 'store'])->name('dashboard.decision-tree.questions.store');
        Route::post('/arbol-decision/{businessUnit}/copiar', [AdminDecisionTreeController::class, 'copy'])->name('dashboard.decision-tree.copy');
        Route::put('/arbol-decision/preguntas/{condition}', [AdminDecisionTreeController::class, 'update'])->name('dashboard.decision-tree.questions.update');
        Route::delete('/arbol-decision/preguntas/{condition}', [AdminDecisionTreeController::class, 'destroy'])->name('dashboard.decision-tree.questions.delete');
        Route::post('/arbol-decision/preguntas/{condition}/mover', [AdminDecisionTreeController::class, 'move'])->name('dashboard.decision-tree.questions.move');

        // Configuraciones
        Route::get('/configuraciones', [AdminConfigurationController::class, 'edit'])->name('dashboard.configurations.edit');
        Route::put('/configuraciones', [AdminConfigurationController::class, 'update'])->name('dashboard.configurations.update');

        // Correos de bienvenida (lista manual)
        Route::get('/correos-bienvenida', [AdminWelcomeBroadcastController::class, 'create'])->name('dashboard.welcome-broadcast.create');
        Route::post('/correos-bienvenida', [AdminWelcomeBroadcastController::class, 'store'])->name('dashboard.welcome-broadcast.store');
    });
