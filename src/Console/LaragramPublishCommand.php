<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Console;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Wekser\Laragram\Support\Aidable;

class LaragramPublishCommand extends Command
{
    use Aidable;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laragram:publish {--force : Overwrite existing files by default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Laragram assets in current application';

    /**
     * The views that need to be exported.
     *
     * @var array
     */
    protected $views = [
        'start.stub' => 'start.php'
    ];

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->confirm('This command will publish Laragram assets on your application. To continue, enters y or yes.')) {

            $this->createDirectories();

            $this->publishViews();

            $this->publishControllers();

            $this->publishRoutes();

            $this->info('The publishing was successful!');
        }
    }

    /**
     * Create the directories for the files.
     *
     * @return void
     */
    protected function createDirectories()
    {
        if (! is_dir($directory = resource_path($this->config('view.path')))) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Publish the views.
     *
     * @return void
     */
    protected function publishViews()
    {
        foreach ($this->views as $key => $value) {
            if (file_exists($view = resource_path($this->config('view.path') . '/' . $value)) && ! $this->option('force')) {
                if (! $this->confirm("The [{$value}] view already exists. Do you want to replace it?")) {
                    continue;
                }
            }

            copy(__DIR__ . '/stubs/views/' . $key, $view);
        }
    }

    /**
     * Publish the example controllers.
     *
     * @return void
     */
    protected function publishControllers()
    {
        if (file_exists($file = app()->path() . '/Http/Controllers/BotController.php') && ! $this->option('force')) {
            if (! $this->confirm("The [{$file}] controller already exists. Do you want to replace it?")) {
                return;
            }
        }

        file_put_contents($file, $this->compileControllerStub());
    }

    /**
     * Publish the bot routes.
     *
     * @return void
     */
    protected function publishRoutes()
    {
        if (file_exists($file = base_path('routes/laragram.php'))) {
            file_put_contents($file, file_get_contents(__DIR__ . '/stubs/routes/laragram.stub', FILE_APPEND));
        }
    }

    /**
     * Compiles the BotController stub.
     *
     * @return string
     */
    protected function compileControllerStub()
    {
        return str_replace(
            '{{namespace}}',
            $this->getAppNamespace(),
            file_get_contents(__DIR__ . '/stubs/controllers/BotController.stub')
        );
    }
}
