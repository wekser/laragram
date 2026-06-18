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

namespace Wekser\Laragram\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FrameHook
{
    /**
     * Reject duplicate updates by checking if the update_id already exists in the sessions table.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (config('laragram.auth.driver') !== 'database') {
            return $next($request);
        }

        $updateId = $request->input('update_id');

        if ($updateId === null) {
            return $next($request);
        }

        $exists = DB::table(config('laragram.auth.session.table'))
            ->where('update_id', (int) $updateId)
            ->exists();

        if ($exists) {
            return response('Already Reported.', 208);
        }

        return $next($request);
    }
}
