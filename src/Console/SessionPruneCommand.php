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

namespace Wekser\Laragram\Console;

use Illuminate\Console\Command;

class SessionPruneCommand extends Command
{
    protected $signature = 'laragram:session:prune
        {--dry-run : Show how many sessions would be deleted without actually deleting}';

    protected $description = 'Delete expired Laragram sessions';

    public function handle(): int
    {
        /** @var class-string $model */
        $model    = config('laragram.auth.session.model');
        $lifetime = (int) config('laragram.auth.session.lifetime', 10080);
        $cutoff   = now()->subMinutes($lifetime)->timestamp;

        $query = $model::where('last_activity', '<', $cutoff);

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info("Would delete {$count} expired session(s) (older than {$lifetime} minutes).");
            return self::SUCCESS;
        }

        $deleted = $query->delete();
        $this->info("Deleted {$deleted} expired session(s).");

        return self::SUCCESS;
    }
}
