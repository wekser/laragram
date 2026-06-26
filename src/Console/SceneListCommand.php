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
use Wekser\Laragram\Exceptions\NotFoundRouteFileException;
use Wekser\Laragram\Scene\SceneRegistry;

/**
 * Lists all registered scenes (wizards) with their steps and options.
 */
class SceneListCommand extends Command
{
    protected $signature = 'laragram:scene:list';

    protected $description = 'List all registered Laragram scenes (wizards)';

    public function handle(): int
    {
        SceneRegistry::flushCache();

        try {
            $scenes = (new SceneRegistry())->all();
        } catch (NotFoundRouteFileException $e) {
            $this->error('Invalid scenes file: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($scenes)) {
            $this->info('No scenes registered.');
            return self::SUCCESS;
        }

        $rows = [];

        foreach ($scenes as $scene) {
            $steps = implode(' → ', array_keys($scene->steps()));

            $rows[] = [
                $scene->name(),
                $steps !== '' ? $steps : '—',
                count($scene->steps()),
                implode(', ', $scene->cancelCommands()) ?: '—',
                $scene->backEnabled() ? implode(', ', $scene->backCommands()) : 'off',
                $scene->timeoutMinutes() !== null ? $scene->timeoutMinutes() . 'm' : '—',
            ];
        }

        $this->table(['Scene', 'Steps', '#', 'Cancel', 'Back', 'Timeout'], $rows);

        return self::SUCCESS;
    }
}
