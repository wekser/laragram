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
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates access to the Laragram admin panel.
 *
 * Resolution order (mirrors Horizon/Telescope):
 *   1. If a "viewLaragram" Gate ability is defined, it decides.
 *   2. Otherwise, the host user's email / auth id must be in config
 *      laragram.admin.allow.
 *   3. Otherwise, access is granted only in the "local" environment.
 */
class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->authorized($request)) {
            abort(403);
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
        $user = $request->user();

        if (Gate::has('viewLaragram')) {
            return Gate::forUser($user)->check('viewLaragram');
        }

        $allow = (array) config('laragram.admin.allow', []);

        if ($allow !== [] && $user !== null) {
            return in_array($user->email ?? null, $allow, true)
                || in_array($user->getAuthIdentifier(), $allow, true);
        }

        return app()->environment('local');
    }
}
