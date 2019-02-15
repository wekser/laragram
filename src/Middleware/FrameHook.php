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
use Wekser\Laragram\Support\Aidable;

class FrameHook
{
    use Aidable;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $rule = ['update_id' => 'required|integer|unique:laragram_sessions,update_id'];

        if ($this->config('auth.driver') == 'database' && app('validator')->make($request->all(), $rule)->fails()) {
            return response('Already Reported.', 208);
        }

        return  $next($request);
    }
}
