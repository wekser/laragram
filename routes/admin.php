<?php

use Illuminate\Support\Facades\Route;
use Wekser\Laragram\Admin\Controllers\BroadcastController;
use Wekser\Laragram\Admin\Controllers\DashboardController;
use Wekser\Laragram\Admin\Controllers\SessionController;
use Wekser\Laragram\Admin\Controllers\UserController;
use Wekser\Laragram\Admin\Middleware\Authorize;

Route::group([
    'prefix'     => config('laragram.admin.path', 'laragram/admin'),
    'middleware' => array_merge((array) config('laragram.admin.middleware', ['web']), [Authorize::class]),
    'as'         => 'laragram.admin.',
], function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users');
    Route::post('users/{id}/role', [UserController::class, 'updateRole'])->name('users.role');
    Route::post('users/{id}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

    Route::get('sessions', [SessionController::class, 'index'])->name('sessions');
    Route::post('sessions/prune', [SessionController::class, 'prune'])->name('sessions.prune');

    Route::get('broadcast', [BroadcastController::class, 'create'])->name('broadcast');
    Route::post('broadcast', [BroadcastController::class, 'store'])->name('broadcast.store');
});
