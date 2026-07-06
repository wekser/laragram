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

            $secret = $this->addVariables();

            $this->info('The installation was successful!');

            $this->newLine();
            $this->line('<fg=yellow>Run the following command to apply migrations:</>');
            $this->line('  php artisan migrate');

            $this->newLine();
            $this->line('<fg=yellow>Optional — to store a payment history (Telegram Stars / invoices):</>');
            $this->line('  php artisan vendor:publish --tag=laragram-migrations');
            $this->line('  (publishes the <fg=cyan>laragram_payments</> table; then set <fg=cyan>LARAGRAM_PAYMENTS_STORE=true</>)');

            $this->newLine();
            $this->line('<fg=yellow>The following variables were added to your .env file:</>');
            $this->line('  LARAGRAM_BOT_TOKEN=<your_bot_token_from_BotFather>');
            $this->line('  LARAGRAM_BOT_USERNAME=<your_bot_username_without_@>  # for group commands');
            $this->line('  LARAGRAM_WEBHOOK_PREFIX=laragram');
            $this->line('  LARAGRAM_WEBHOOK_SECRET=' . $secret);
            $this->line('  LARAGRAM_VERIFY_SECRET=true');

            $this->newLine();
            $this->line('<fg=yellow>Set your bot token, then register the webhook:</>');
            $this->line('  php artisan laragram:webhook:set');
            $this->line('  (or use <fg=cyan>laragram:poll</> for local development without a public URL)');

            $this->newLine();
            $this->line('<fg=yellow>Add session cleanup to your scheduler (app/Console/Kernel.php or routes/console.php):</>');
            $this->line("  \$schedule->command('laragram:session:prune')->daily();");
            $this->newLine();
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
        if (file_exists($file = base_path('config/laragram.php')) && !$this->option('force')) {
            if (!$this->confirm("The [{$file}] config already exists. Do you want to replace it?")) {
                return;
            }
        }

        copy(__DIR__ . '/../../config/laragram.php', $file);
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

        sleep(2);

        file_put_contents(
            base_path('database/migrations/' . date('Y_m_d_His', time()) . '_create_laragram_sessions_table.php'),
            file_get_contents(__DIR__ . '/stubs/migrations/create_laragram_sessions_table.stub')
        );
    }

    /**
     * Create the bot route and scene files (default: routes/laragram/).
     *
     * @return void
     */
    protected function createRoutes()
    {
        $this->createRouteFile(
            (string) config('laragram.paths.route', 'laragram/routes'),
            __DIR__ . '/stubs/routes/routes.stub',
            'route'
        );

        $this->createRouteFile(
            (string) config('laragram.paths.scenes', 'laragram/scenes'),
            __DIR__ . '/stubs/routes/scenes_blank.stub',
            'scenes'
        );
    }

    /**
     * Copy a route/scenes stub to its configured location under routes/,
     * creating the (sub)directory and honouring --force / confirmation.
     *
     * @return void
     */
    protected function createRouteFile(string $name, string $stub, string $label)
    {
        $file = base_path("routes/{$name}.php");

        if (file_exists($file) && !$this->option('force')) {
            if (!$this->confirm("The [{$file}] {$label} file already exists. Do you want to replace it?")) {
                return;
            }
        }

        if (!is_dir($directory = dirname($file))) {
            mkdir($directory, 0755, true);
        }

        copy($stub, $file);
    }

    /**
     * Add variables and generate secret token in .env file.
     *
     * @return string Generated webhook secret.
     */
    protected function addVariables(): string
    {
        $file   = base_path('.env');
        $secret = Str::random(18);

        if (file_exists($file)) {
            $vars = PHP_EOL
                . 'LARAGRAM_BOT_TOKEN=' . PHP_EOL
                . 'LARAGRAM_BOT_USERNAME=' . PHP_EOL
                . 'LARAGRAM_WEBHOOK_PREFIX=laragram' . PHP_EOL
                . 'LARAGRAM_WEBHOOK_SECRET=' . $secret . PHP_EOL
                . 'LARAGRAM_VERIFY_SECRET=true' . PHP_EOL;

            file_put_contents($file, $vars, FILE_APPEND);
        } else {
            $this->comment('.env file in base path your application not found.');
        }

        return $secret;
    }
}
