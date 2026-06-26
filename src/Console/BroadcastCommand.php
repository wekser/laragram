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
use Wekser\Laragram\Broadcasting\Broadcaster;

class BroadcastCommand extends Command
{
    protected $signature = 'laragram:broadcast
        {message? : Raw text to broadcast (omit when using --view)}
        {--view= : View directory name to render per recipient}
        {--role=* : Restrict recipients to one or more roles}
        {--include-inactive : Also send to deactivated users}
        {--dry-run : Print the recipient count and exit without sending}
        {--no-confirm : Skip the confirmation prompt}';

    protected $description = 'Broadcast a message to your bot users';

    public function handle(): int
    {
        if (config('laragram.auth.driver') !== 'database') {
            $this->error('Broadcasting requires the "database" auth driver — there are no persisted users under the "array" driver.');
            return self::FAILURE;
        }

        $message = $this->argument('message');
        $view    = $this->option('view');

        // `--view=` yields '' (not null); treat a blank view as absent so the
        // exactly-one guard below cannot be bypassed into rendering view('').
        if ($view === '') {
            $view = null;
        }

        if (($message === null) === ($view === null)) {
            $this->error('Provide exactly one of: a message argument OR the --view option.');
            return self::FAILURE;
        }

        /** @var Broadcaster $broadcaster */
        $broadcaster = app('laragram.broadcast');

        $pending = $view !== null
            ? $broadcaster->view($view)
            : $broadcaster->text((string) $message);

        $roles = (array) $this->option('role');

        if ($roles !== []) {
            $pending->role($roles);
        }

        if ($this->option('include-inactive')) {
            $pending->includeInactive();
        }

        $count = $pending->count();

        if ($count === 0) {
            $this->warn('No recipients match the given filters.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Dry run: {$count} recipient(s) would receive this broadcast.");
            return self::SUCCESS;
        }

        if (!$this->option('no-confirm') && !$this->confirm("Send this broadcast to {$count} user(s)?")) {
            $this->line('Aborted.');
            return self::SUCCESS;
        }

        $result = $pending->send();

        if ($result->queued > 0) {
            $this->info("Queued {$result->queued} broadcast job(s). Run a queue worker to deliver them.");
        } else {
            $this->info("Broadcast complete: {$result->sent} sent, {$result->failed} failed (of {$result->total}).");
        }

        return self::SUCCESS;
    }
}
