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
use Illuminate\Container\Container;

class LaragramPublishCommand extends Command
{
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
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->confirm('This command will publish Laragram assets on your application. To continue, enters y or yes.')) {
            $this->call('vendor:publish', ['--tag' => 'laragram-views', '--force' => $this->option('force')]);
            $this->call('vendor:publish', ['--tag' => 'laragram-lang', '--force' => $this->option('force')]);
            $this->createRoute();
            $this->createController();
            $this->info('Laragram views, translations, routes and HelloController were published.');
        }
    }

    /**
     * Publish (or append) demo routes to the bot route file.
     *
     * @return void
     */
    protected function createRoute(): void
    {
        $routeName = config('laragram.paths.route', 'laragram');
        $file      = base_path("routes/{$routeName}.php");
        $stub      = file_get_contents(__DIR__ . '/stubs/routes/laragram.stub');

        if (!file_exists($file)) {
            file_put_contents($file, $stub);
            $this->info("Route file [{$file}] created.");
            return;
        }

        if ($this->option('force')) {
            file_put_contents($file, $stub);
            $this->info("Route file [{$file}] overwritten.");
            return;
        }

        // Strip `<?php` and the opening block comment so we only append the route body.
        $appendable = preg_replace('/^<\?php\s*\/\*.*?\*\/\s*/s', '', $stub);

        // Separate use-import lines from the rest of the body.
        $lines        = explode("\n", $appendable);
        $useLines     = [];
        $bodyLines    = [];
        $pastUseBlock = false;

        foreach ($lines as $line) {
            if (!$pastUseBlock && preg_match('/^use\s+/', $line)) {
                $useLines[] = $line;
            } else {
                $pastUseBlock = true;
                $bodyLines[]  = $line;
            }
        }

        $existing = file_get_contents($file);

        // Only include use statements that are not already in the file.
        $newUses = array_filter($useLines, fn($use) => !str_contains($existing, trim($use)));

        $append  = "\n// --- Demo routes (added by laragram:publish) ---\n";
        if (!empty($newUses)) {
            $append .= implode("\n", $newUses) . "\n";
        }
        $append .= ltrim(implode("\n", $bodyLines));

        file_put_contents($file, rtrim($existing) . "\n" . $append);
        $this->info("Demo routes appended to [{$file}].");
    }

    /**
     * Publish the example HelloController to the application.
     *
     * @return void
     */
    protected function createController()
    {
        $directory = app_path('Http/Controllers/Laragram');
        $file      = $directory . '/HelloController.php';

        if (file_exists($file) && !$this->option('force')) {
            if (!$this->confirm("The [{$file}] controller already exists. Do you want to replace it?")) {
                return;
            }
        }

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $stub = file_get_contents(__DIR__ . '/stubs/controllers/HelloController.stub');
        $stub = str_replace('{{namespace}}', $this->getAppNamespace(), $stub);

        file_put_contents($file, $stub);
    }

    /**
     * Get the application namespace.
     *
     * @return string
     */
    protected function getAppNamespace(): string
    {
        return Container::getInstance()->getNamespace();
    }
}
