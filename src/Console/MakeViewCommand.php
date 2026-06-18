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

class MakeViewCommand extends Command
{
    protected $signature = 'laragram:make:view
        {name : View name in dot-notation (e.g. welcome, menu.main)}
        {--with= : Comma-separated extra components to scaffold (photo,video,document,audio,voice,animation,sticker,video_note,media,inline_keyboard,reply_keyboard)}
        {--force : Overwrite existing component files}';

    protected $description = 'Create a new Laragram component view (directory with text.php)';

    /**
     * Component files that can be scaffolded via --with=.
     * Each key maps to the stub filename (without .stub).
     */
    private const COMPONENTS = [
        'photo'           => 'file_id',
        'video'           => 'file_id',
        'document'        => 'file_id',
        'audio'           => 'file_id',
        'voice'           => 'file_id',
        'animation'       => 'file_id',
        'sticker'         => 'file_id',
        'video_note'      => 'file_id',
        'media'           => 'media',
        'inline_keyboard' => 'inline_keyboard',
        'reply_keyboard'  => 'reply_keyboard',
    ];

    public function handle(): int
    {
        $name      = str_replace('.', '/', (string) $this->argument('name'));
        $viewsDir  = (string) config('laragram.paths.views', 'laragram');
        $targetDir = resource_path($viewsDir . '/' . $name);

        // Always scaffold text.php
        $components = ['text'];

        // Parse --with= option
        if ($this->option('with')) {
            $requested = array_map('trim', explode(',', (string) $this->option('with')));

            foreach ($requested as $component) {
                if (!array_key_exists($component, self::COMPONENTS)) {
                    $this->error("Unknown component [{$component}]. Available: " . implode(', ', array_keys(self::COMPONENTS)));
                    return self::FAILURE;
                }

                $components[] = $component;
            }
        }

        File::ensureDirectoryExists($targetDir);

        $created = [];
        $skipped = [];

        foreach ($components as $component) {
            $destination = $targetDir . '/' . $component . '.php';
            $stub        = self::COMPONENTS[$component] ?? $component;
            $stubPath    = __DIR__ . '/stubs/views/' . $stub . '.stub';

            if (File::exists($destination) && !$this->option('force')) {
                $skipped[] = $component . '.php';
                continue;
            }

            File::put($destination, File::get($stubPath));
            $created[] = $component . '.php';
        }

        if (!empty($created)) {
            $this->info("View [{$targetDir}] created: " . implode(', ', $created));
        }

        if (!empty($skipped)) {
            $this->warn("Skipped (already exist, use --force to overwrite): " . implode(', ', $skipped));
        }

        return self::SUCCESS;
    }
}
