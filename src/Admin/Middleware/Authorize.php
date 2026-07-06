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

namespace Wekser\Laragram\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates access to the Laragram admin panel.
 *
 * Resolution order:
 *   1. If a "viewLaragram" Gate ability is defined, it decides (an escape hatch
 *      for host apps that want to reuse their own web auth). A denying Gate is a
 *      hard 403.
 *   2. Otherwise the panel is protected by the "laragram_admin" session guard
 *      (the laragram_admins table); an unauthenticated visitor is redirected to
 *      the login page. Create accounts with `php artisan laragram:admin:create`.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->authorized($request)) {
            // A defined Gate that denies is a hard 403; otherwise send the
            // visitor to the login page to authenticate as a Laragram admin.
            if (Gate::has('viewLaragram')) {
                abort(403);
            }

            return redirect()->guest(route('laragram.admin.login'));
        }

        // The panel reads the persisted user/session tables; under the "array"
        // driver those do not exist, so fail with a clear message instead of an
        // opaque QueryException 500 from a controller query.
        if (config('laragram.auth.driver') !== 'database') {
            abort(503, 'The Laragram admin panel requires the "database" auth driver.');
        }

        return $next($request);
    }

    private function authorized(Request $request): bool
    {
        if (Gate::has('viewLaragram')) {
            return Gate::forUser($request->user())->check('viewLaragram');
        }

        return Auth::guard(config('laragram.admin.guard', 'laragram_admin'))->check();
    }
}
