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

class LaragramInstallCommand extends Command
{
    use Aidable;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laragram:install {--force : Overwrite existing files by default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laragram in current application';

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
        if ($this->confirm('This command will install Laragram on your application. To continue, enters y or yes.')) {

            $this->createDirectories();

            $this->createConfig();

            $this->createViews();

            $this->createControllers();

            $this->createMigrations();

            $this->createRoutes();

            $this->addVariables();

            $this->info('The installation was successful!');
        }
    }

    /**
     * Create the directories for the files.
     *
     * @return void
     */
    protected function createDirectories()
    {
        if (! is_dir($directory = base_path('config'))) {
            mkdir($directory, 0755, true);
        }

        if (! is_dir($directory = resource_path($this->config('view.path')))) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Publish the config.
     *
     * @return void
     */
    protected function createConfig()
    {
        if (file_exists(base_path('config/laragram.php')) && ! $this->option('force')) {
            if (! $this->confirm("The [laragram.php] config already exists. Do you want to replace it?")) {
                return;
            }
        }

        copy(__DIR__ . '/../../config/config.php', base_path('config/laragram.php'));
    }

    /**
     * Create the views.
     *
     * @return void
     */
    protected function createViews()
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
     * Create the example controllers.
     *
     * @return void
     */
    protected function createControllers()
    {
        if (file_exists($file = app()->path() . '/Http/Controllers/BotController.php') && ! $this->option('force')) {
            if (! $this->confirm("The [{$file}] controller already exists. Do you want to replace it?")) {
                return;
            }
        }

        file_put_contents($file, $this->compileControllerStub());
    }

    /**
     * Create the database migrations.
     *
     * @return void
     */
    protected function createMigrations()
    {
        file_put_contents(
            base_path('database/migrations/'.date('Y_m_d_His', time()).'_create_laragram_users_table.php'),
            file_get_contents(__DIR__.'/stubs/migrations/create_laragram_users_table.stub')
        );

        file_put_contents(
            base_path('database/migrations/'.date('Y_m_d_His', time()).'_create_laragram_sessions_table.php'),
            file_get_contents(__DIR__ . '/stubs/migrations/create_laragram_sessions_table.stub')
        );
    }

    /**
     * Create the application routes.
     *
     * @return void
     */
    protected function createRoutes()
    {
        if (file_exists($file = base_path('routes/laragram.php')) && ! $this->option('force')) {
            if (! $this->confirm("The [{$file}] route already exists. Do you want to replace it?")) {
                return;
            }
        }

        file_put_contents($file, file_get_contents(__DIR__ . '/stubs/routes/laragram.stub'));
    }

    /**
     * Add variables and generate secret token in .env file
     *
     * @return string
     */
    protected function addVariables()
    {
        $file = base_path('.env');
        $key = Str::random(18);

        if (file_exists($file)) {
            file_put_contents(
                $file,
                PHP_EOL . 'LARAGRAM_BOT_TOKEN=' . PHP_EOL . 'LARAGRAM_WEBHOOK_SECRET=' . $key . PHP_EOL,
                FILE_APPEND);
        } else {
            $this->comment('.env file in base path your application not found.');
        }
    }

    /**
     * Compiles the HomeController stub.
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
