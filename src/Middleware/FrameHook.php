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

class FrameHook
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
        $rule = ['update_id' => 'required|integer|unique:' . config('laragram.auth.session.table') . ',update_id'];

        if (config('laragram.auth.driver') == 'database' && app('validator')->make($request->all(), $rule)->fails()) {
            return response('Already Reported.', 208);
        }

        return $next($request);
    }
}
