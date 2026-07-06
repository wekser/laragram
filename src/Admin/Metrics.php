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

namespace Wekser\Laragram\Admin;

use Illuminate\Support\Carbon;

/**
 * Computes the aggregate numbers shown on the admin dashboard from the bot's
 * user and session tables. Read-only; every value is derived from the database
 * so it works without any extra tracking table.
 */
class Metrics
{
    /**
     * @return array{
     *     total: int, active: int, inactive: int,
     *     new_today: int, new_week: int,
     *     roles: array<string, int>,
     *     active_sessions: int
     * }
     */
    public function summary(): array
    {
        $user    = $this->userModel();
        $now     = Carbon::now();

        $total  = $user::query()->count();
        $active = $user::query()->where('is_active', true)->count();

        return [
            'total'           => $total,
            'active'          => $active,
            'inactive'        => $total - $active,
            'new_today'       => $user::query()->where('created_at', '>=', $now->copy()->startOfDay())->count(),
            'new_week'        => $user::query()->where('created_at', '>=', $now->copy()->subDays(7))->count(),
            'roles'           => $this->roleBreakdown(),
            'active_sessions' => $this->activeSessions(),
        ];
    }

    /**
     * Count of users per role, ordered by size.
     *
     * @return array<string, int>
     */
    public function roleBreakdown(): array
    {
        $user = $this->userModel();

        return $user::query()
            ->selectRaw('role, COUNT(*) as aggregate')
            ->groupBy('role')
            ->orderByDesc('aggregate')
            ->pluck('aggregate', 'role')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * Sessions active within the configured lifetime window.
     */
    public function activeSessions(): int
    {
        $session  = $this->sessionModel();
        $lifetime = (int) config('laragram.auth.session.lifetime', 10080);

        return $session::query()
            ->where('last_activity', '>=', Carbon::now()->subMinutes($lifetime))
            ->count();
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function userModel(): string
    {
        return config('laragram.auth.user.model');
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function sessionModel(): string
    {
        return config('laragram.auth.session.model');
    }
}
