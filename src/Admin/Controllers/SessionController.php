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
use Illuminate\Support\Carbon;

class SessionController
{
    /**
     * List recent sessions with their user and station.
     */
    public function index(): View
    {
        $model = $this->sessionModel();

        return view('laragram::admin.sessions', [
            'sessions' => $model::query()
                ->with('user')
                ->orderByDesc('last_activity')
                ->paginate(25),
            'lifetime' => (int) config('laragram.auth.session.lifetime', 10080),
        ]);
    }

    /**
     * Delete sessions that fell outside the configured lifetime window.
     */
    public function prune(): RedirectResponse
    {
        $lifetime = (int) config('laragram.auth.session.lifetime', 10080);

        $deleted = $this->sessionModel()::query()
            ->where('last_activity', '<', Carbon::now()->subMinutes($lifetime))
            ->delete();

        return back()->with('status', "Pruned {$deleted} expired session(s).");
    }

    /** @return class-string<\Wekser\Laragram\Models\Session> */
    private function sessionModel(): string
    {
        return config('laragram.auth.session.model');
    }
}
