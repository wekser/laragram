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
use Wekser\Laragram\Admin\Metrics;

class DashboardController
{
    /**
     * Show the metrics dashboard (the panel home page).
     */
    public function index(Metrics $metrics): View
    {
        return view('laragram::admin.dashboard', [
            'metrics' => $metrics->summary(),
        ]);
    }
}
