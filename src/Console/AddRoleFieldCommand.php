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

/**
 * Command to add role field migration.
 *
 * @package Wekser\Laragram\Console
 */
class AddRoleFieldCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laragram:add-role-field';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add role field to the users table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Adding role field migration...');

        $tableName = config('laragram.auth.user.table', 'laragram_users');
        $stubPath  = __DIR__ . '/stubs/migrations/add_role_field.stub';

        if (!File::exists($stubPath)) {
            $this->error('Migration stub not found!');
            return 1;
        }

        $stub = File::get($stubPath);
        $stub = str_replace('{{ $table }}', $tableName, $stub);

        $migrationName = 'add_role_field_to_' . $tableName . '_table';
        $migrationPath = database_path('migrations/' . date('Y_m_d_His') . '_' . $migrationName . '.php');

        File::put($migrationPath, $stub);

        $this->info("Migration created: {$migrationPath}");
        $this->info('Run "php artisan migrate" to apply the migration.');

        return 0;
    }
}
