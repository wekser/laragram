<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
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
        $entity = collect($request->all())->first(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        });

        $user = collect($entity)->get('from');

        if (!empty($user) && !$user['is_bot']) {
            return $next($request);
        }

        return response('Unauthorized.', 401);
    }
}
