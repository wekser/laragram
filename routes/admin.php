<?php

use Illuminate\Support\Facades\Route;
use Wekser\Laragram\Admin\Controllers\AuthController;
use Wekser\Laragram\Admin\Controllers\BroadcastController;
use Wekser\Laragram\Admin\Controllers\DashboardController;
use Wekser\Laragram\Admin\Controllers\SessionController;
use Wekser\Laragram\Admin\Controllers\UserController;
use Wekser\Laragram\Admin\Middleware\Authorize;

$web = (array) config('laragram.admin.middleware', ['web']);

Route::group([
    'prefix' => config('laragram.admin.path', 'laragram/admin'),
    'as'     => 'laragram.admin.',
], function () use ($web): void {
    // Auth routes — reachable without being logged in (only the "web" group,
    // NOT the Authorize gate, so there is no redirect loop to the login page).
    Route::group(['middleware' => $web], function (): void {
        Route::get('login', [AuthController::class, 'show'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });

    // Panel routes — behind the Authorize gate (login or the viewLaragram Gate).
    Route::group(['middleware' => array_merge($web, [Authorize::class])], function (): void {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('users', [UserController::class, 'index'])->name('users');
        Route::post('users/{id}/role', [UserController::class, 'updateRole'])->name('users.role');
        Route::post('users/{id}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

        Route::get('sessions', [SessionController::class, 'index'])->name('sessions');
        Route::post('sessions/prune', [SessionController::class, 'prune'])->name('sessions.prune');

        Route::get('broadcast', [BroadcastController::class, 'create'])->name('broadcast');
        Route::post('broadcast', [BroadcastController::class, 'store'])->name('broadcast.store');
    });
});
