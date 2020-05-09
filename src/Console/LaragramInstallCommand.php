<?php

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
use Illuminate\Support\Str;

class LaragramInstallCommand extends Command
{
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
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->confirm('This command will install Laragram on your application. To continue, enters y or yes.')) {

            $this->createDirectories();

            $this->createConfig();

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
        if (!is_dir($directory = base_path('config'))) {
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
        if (file_exists(base_path('config/laragram.php')) && !$this->option('force')) {
            if (!$this->confirm("The [laragram.php] config already exists. Do you want to replace it?")) {
                return;
            }
        }

        copy(__DIR__ . '/../../config/config.php', base_path('config/laragram.php'));
    }

    /**
     * Create the database migrations.
     *
     * @return void
     */
    protected function createMigrations()
    {
        file_put_contents(
            base_path('database/migrations/' . date('Y_m_d_His', time()) . '_create_laragram_users_table.php'),
            file_get_contents(__DIR__ . '/stubs/migrations/create_laragram_users_table.stub')
        );

        file_put_contents(
            base_path('database/migrations/' . date('Y_m_d_His', time()) . '_create_laragram_sessions_table.php'),
            file_get_contents(__DIR__ . '/stubs/migrations/create_laragram_sessions_table.stub')
        );
    }

    /**
     * Create the bot routes.
     *
     * @return void
     */
    protected function createRoutes()
    {
        if (file_exists($file = base_path('routes/laragram.php')) && !$this->option('force')) {
            if (!$this->confirm("The [{$file}] route already exists. Do you want to replace it?")) {
                return;
            }
        }

        copy(__DIR__ . '/stubs/routes/routes.stub', $file);
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
}
