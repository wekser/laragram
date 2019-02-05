<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAuth
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = array_get(array_get($request->all(), array_get(array_keys($request->all()), 1)), 'from');

        if (! empty($user) && array_get($user, 'is_bot') == false) {
            return $next($request);
        }

        return response('Unauthorized.', 401);
    }
}
