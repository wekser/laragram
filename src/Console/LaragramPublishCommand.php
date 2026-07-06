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
            $this->createScenes();
            $this->createControllers();
            $this->info('Laragram views, translations, routes, scenes and demo controllers were published.');
        }
    }

    /**
     * Publish (or append) demo routes to the bot route file.
     *
     * @return void
     */
    protected function createRoute(): void
    {
        $this->publishDemoStub(
            (string) config('laragram.paths.route', 'laragram/routes'),
            __DIR__ . '/stubs/routes/laragram.stub',
            'Demo routes',
            '// --- Demo routes (added by laragram:publish) ---'
        );
    }

    /**
     * Publish (or append) the demo "order" scene to the scenes file.
     *
     * The bundled scene defines the multi-step "order" wizard that the demo
     * routes enter via the /order command; it must land alongside those routes
     * or the appended /order route would reference an undefined scene.
     *
     * @return void
     */
    protected function createScenes(): void
    {
        $this->publishDemoStub(
            (string) config('laragram.paths.scenes', 'laragram/scenes'),
            __DIR__ . '/stubs/routes/scenes.stub',
            'Demo scene',
            '// --- Demo scene (added by laragram:publish) ---'
        );
    }

    /**
     * Publish a demo stub under routes/: create the file when absent, overwrite
     * on --force, otherwise append the stub body (deduplicating use-imports) to
     * the existing file. The $marker sentinel makes the append idempotent so a
     * second publish does not duplicate the demo block.
     *
     * @return void
     */
    protected function publishDemoStub(string $name, string $stubPath, string $label, string $marker): void
    {
        $file = base_path("routes/{$name}.php");
        $stub = file_get_contents($stubPath);

        if (!is_dir($directory = dirname($file))) {
            mkdir($directory, 0755, true);
        }

        if (!file_exists($file)) {
            file_put_contents($file, $stub);
            $this->info("{$label} file [{$file}] created.");
            return;
        }

        if ($this->option('force')) {
            file_put_contents($file, $stub);
            $this->info("{$label} file [{$file}] overwritten.");
            return;
        }

        $existing = file_get_contents($file);

        // Idempotent: the demo block was already appended by a previous publish.
        if (str_contains($existing, $marker)) {
            $this->warn("{$label} already present in [{$file}]; skipped (use --force to overwrite).");
            return;
        }

        // Strip `<?php` and the opening block comment so we only append the body.
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

        // Only include use statements that are not already in the file.
        $newUses = array_filter($useLines, fn($use) => !str_contains($existing, trim($use)));

        $append = "\n{$marker}\n";
        if (!empty($newUses)) {
            $append .= implode("\n", $newUses) . "\n";
        }
        $append .= ltrim(implode("\n", $bodyLines));

        file_put_contents($file, rtrim($existing) . "\n" . $append);
        $this->info("{$label} appended to [{$file}].");
    }

    /**
     * Publish the example bot controllers to the application.
     *
     * HelloController powers the /start echo demo; OrderController drives the
     * /order scene demo; ExtrasController demos payments (Stars), inline mode,
     * and receiving files.
     *
     * @return void
     */
    protected function createControllers()
    {
        $directory = app_path('Http/Controllers/Laragram');

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach (['HelloController', 'OrderController', 'ExtrasController'] as $controller) {
            $file = $directory . "/{$controller}.php";

            if (file_exists($file) && !$this->option('force')) {
                if (!$this->confirm("The [{$file}] controller already exists. Do you want to replace it?")) {
                    continue;
                }
            }

            $stub = file_get_contents(__DIR__ . "/stubs/controllers/{$controller}.stub");
            $stub = str_replace('{{namespace}}', $this->getAppNamespace(), $stub);

            file_put_contents($file, $stub);
        }
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
