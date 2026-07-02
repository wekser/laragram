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
use Illuminate\Support\Facades\File;
use Wekser\Laragram\Support\RouteFile;

/**
 * Scaffolds a new scene (wizard) definition by appending a BotScene::define()
 * block to the scenes file (routes/{config laragram.paths.scenes}.php, default
 * routes/laragram/scenes.php), creating the file with the required imports if it
 * does not exist yet.
 */
class MakeSceneCommand extends Command
{
    protected $signature = 'laragram:make:scene
        {name : Scene name (e.g. order)}
        {--steps= : Comma-separated step keys (default: one "step" step)}';

    protected $description = 'Scaffold a new Laragram scene (wizard) definition';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
            $this->error("Invalid scene name [{$name}]. Use letters, digits and underscores.");
            return self::FAILURE;
        }

        $fileName = (string) config('laragram.paths.scenes', 'laragram/scenes');

        if (!RouteFile::isValidName($fileName)) {
            $this->error("Invalid scenes file name [{$fileName}].");
            return self::FAILURE;
        }

        $path = RouteFile::path($fileName);

        if (File::exists($path) && str_contains(File::get($path), "BotScene::define('{$name}')")) {
            $this->error("Scene [{$name}] is already defined in {$path}.");
            return self::FAILURE;
        }

        $steps = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($this->option('steps') ?: 'step'))
        )));

        if (!File::exists($path)) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $this->header());
        }

        File::append($path, $this->sceneBlock($name, $steps));

        $this->info("Scene [{$name}] added to {$path}.");
        $this->line("Enter it from a route handler with: <comment>return BotScene::enter('{$name}');</comment>");

        return self::SUCCESS;
    }

    /**
     * File preamble written when the scenes file is created.
     */
    private function header(): string
    {
        return <<<'PHP'
        <?php

        /*
        |--------------------------------------------------------------------------
        | Laragram Scenes
        |--------------------------------------------------------------------------
        */

        use Wekser\Laragram\Facades\BotResponse;
        use Wekser\Laragram\Facades\BotScene;

        PHP;
    }

    /**
     * Build the BotScene::define() block for the given steps.
     *
     * @param string[] $steps
     */
    private function sceneBlock(string $name, array $steps): string
    {
        $lines = ["\nBotScene::define('{$name}')"];

        foreach ($steps as $step) {
            $lines[] = "    ->step('{$step}')";
            $lines[] = "        ->ask(fn (\$ctx) => BotResponse::text('{$step}?'))";
            $lines[] = "        ->rules(['required'])";
        }

        $lines[] = "    ->onComplete(fn (\$ctx) => BotResponse::text('Done')->redirect('start'));\n";

        return implode("\n", $lines);
    }
}
