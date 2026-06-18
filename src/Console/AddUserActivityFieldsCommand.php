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
 * Command to add user activity fields migration
 * 
 * @package Wekser\Laragram\Console
 */
class AddUserActivityFieldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laragram:add-user-activity-fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add user activity fields (is_active, deactivated_at) to users table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Adding user activity fields migration...');

        $tableName = config('laragram.auth.user.table', 'laragram_users');
        $stubPath = __DIR__ . '/stubs/migrations/add_user_activity_fields.stub';
        
        if (!File::exists($stubPath)) {
            $this->error('Migration stub not found!');
            return 1;
        }

        $stub = File::get($stubPath);
        $stub = str_replace('{{ $table }}', $tableName, $stub);

        $migrationName = 'add_user_activity_fields_to_' . $tableName . '_table';
        $migrationPath = database_path('migrations/' . date('Y_m_d_His') . '_' . $migrationName . '.php');

        File::put($migrationPath, $stub);

        $this->info("Migration created: {$migrationPath}");
        $this->info('Run "php artisan migrate" to apply the migration.');

        return 0;
    }
}




