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

namespace Wekser\Laragram\Tests\Unit\Console;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Providers\LaragramServiceProvider;
use Wekser\Laragram\Tests\TestCase;

/**
 * The `laragram-migrations` publish tag must expose ONLY the opt-in payments
 * migration — the base users/sessions/admins tables are created (timestamped)
 * by laragram:install, and publishing them here too would duplicate the tables.
 * The published payments file must also carry a valid timestamp prefix so it is
 * a runnable migration ordered after the install ones.
 */
#[CoversClass(LaragramServiceProvider::class)]
class MigrationPublishTagTest extends TestCase
{
    public function test_tag_publishes_only_the_payments_migration(): void
    {
        $paths = ServiceProvider::pathsToPublish(LaragramServiceProvider::class, 'laragram-migrations');

        $this->assertCount(1, $paths, 'The tag must publish exactly one migration.');

        $source = array_key_first($paths);
        $this->assertStringEndsWith('create_laragram_payments_table.stub', $source);
    }

    public function test_published_payments_migration_has_a_timestamp_prefix(): void
    {
        $paths = ServiceProvider::pathsToPublish(LaragramServiceProvider::class, 'laragram-migrations');

        $destination = basename((string) reset($paths));

        $this->assertMatchesRegularExpression(
            '/^\d{4}_\d{2}_\d{2}_\d{6}_create_laragram_payments_table\.php$/',
            $destination,
            'The published migration must have a Y_m_d_His timestamp prefix.'
        );
    }

    public function test_tag_never_republishes_the_base_tables(): void
    {
        $paths = ServiceProvider::pathsToPublish(LaragramServiceProvider::class, 'laragram-migrations');

        $joined = implode('|', array_keys($paths));

        // These three are install's responsibility; re-publishing would duplicate them.
        $this->assertStringNotContainsString('create_laragram_users_table', $joined);
        $this->assertStringNotContainsString('create_laragram_sessions_table', $joined);
        $this->assertStringNotContainsString('create_laragram_admins_table', $joined);
    }
}
