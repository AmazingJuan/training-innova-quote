<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminUserController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->select('id', 'name', 'email', 'phone')
            ->search($search, ['name', 'email', 'phone'])
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'viewData' => [
                'users' => $users,
                'filters' => ['search' => $search],
            ],
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('admin/users/create');
    }

    public function store(AdminUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return to_route('dashboard.users.index')->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): InertiaResponse
    {
        return Inertia::render('admin/users/edit', [
            'viewData' => [
                'user' => $user->only('id', 'name', 'email', 'phone'),
            ],
        ]);
    }

    public function update(AdminUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return to_route('dashboard.users.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            return to_route('dashboard.users.index')->with('error', 'No puedes eliminar tu propio usuario.');
        }

        $user->delete();

        return to_route('dashboard.users.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
