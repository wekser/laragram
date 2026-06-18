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

class MakeControllerCommand extends Command
{
    protected $signature = 'laragram:make:controller
        {name : Controller class name (e.g. StartController)}
        {--force : Overwrite if the file already exists}';

    protected $description = 'Create a new Laragram bot controller';

    public function handle(): int
    {
        $name        = (string) $this->argument('name');
        $namespace   = $this->laravel->getNamespace();
        $destination = app_path('Http/Controllers/Laragram/' . $name . '.php');

        if (File::exists($destination) && !$this->option('force')) {
            $this->error("Controller [{$destination}] already exists.");
            return self::FAILURE;
        }

        $stub = File::get(__DIR__ . '/stubs/controllers/controller.stub');
        $stub = str_replace(['{{namespace}}', '{{class}}'], [$namespace, $name], $stub);

        File::ensureDirectoryExists(dirname($destination));
        File::put($destination, $stub);

        $this->info("Controller [{$destination}] created successfully.");

        return self::SUCCESS;
    }
}
