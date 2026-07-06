<?php
declare(strict_types=1);

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Admin\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserController
{
    /**
     * List bot users with search and role/status filters.
     */
    public function index(Request $request): View
    {
        $model = $this->userModel();
        $query = $model::query();

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('uid', 'like', "%{$search}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if (($status = $request->query('status')) === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        return view('laragram::admin.users', [
            'users'   => $query->orderByDesc('id')->paginate(25)->withQueryString(),
            'roles'   => $model::query()->select('role')->distinct()->orderBy('role')->pluck('role'),
            'filters' => [
                'search' => $search,
                'role'   => $role,
                'status' => $status,
            ],
        ]);
    }

    /**
     * Assign a role to a user.
     */
    public function updateRole(Request $request, int|string $id): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', 'string', 'max:50']]);

        $user = $this->userModel()::query()->findOrFail($id);
        $user->update(['role' => $data['role']]);

        return back()->with('status', "Role for {$user->first_name} set to “{$data['role']}”.");
    }

    /**
     * Activate or deactivate a user.
     */
    public function toggleActive(int|string $id): RedirectResponse
    {
        $user = $this->userModel()::query()->findOrFail($id);

        $user->isActive() ? $user->deactivate() : $user->activate();

        $state = $user->isActive() ? 'activated' : 'deactivated';

        return back()->with('status', "User {$user->first_name} {$state}.");
    }

    /** @return class-string<\Wekser\Laragram\Models\User> */
    private function userModel(): string
    {
        return config('laragram.auth.user.model');
    }
}
