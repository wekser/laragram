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

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Console\BroadcastCommand;
use Wekser\Laragram\Testing\RecordingBotAPI;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(BroadcastCommand::class)]
class BroadcastCommandTest extends TestCase
{
    use UsesUserDatabase;

    public function test_fails_under_the_array_driver(): void
    {
        // Default driver is 'array' (no setUpUserDatabase call).
        $this->artisan('laragram:broadcast', ['message' => 'hi', '--no-confirm' => true])
            ->assertFailed();
    }

    public function test_fails_when_neither_message_nor_view_given(): void
    {
        $this->setUpUserDatabase();

        $this->artisan('laragram:broadcast', ['--no-confirm' => true])
            ->assertFailed();
    }

    public function test_fails_when_both_message_and_view_given(): void
    {
        $this->setUpUserDatabase();

        $this->artisan('laragram:broadcast', ['message' => 'hi', '--view' => 'broadcast_view', '--no-confirm' => true])
            ->assertFailed();
    }

    public function test_fails_when_view_option_is_blank(): void
    {
        $this->setUpUserDatabase();

        // `--view=` (empty) must be treated as absent, not as the view name '',
        // so the exactly-one guard still fires instead of broadcasting view('').
        $this->artisan('laragram:broadcast', ['--view' => '', '--no-confirm' => true])
            ->assertFailed();
    }

    public function test_dry_run_reports_recipient_count_without_sending(): void
    {
        $this->setUpUserDatabase();
        $api = new RecordingBotAPI();
        $this->app->instance('laragram.api', $api);

        $this->makeUser();
        $this->makeUser();

        $this->artisan('laragram:broadcast', ['message' => 'hi', '--dry-run' => true])
            ->expectsOutputToContain('2 recipient(s)')
            ->assertSuccessful();

        $this->assertCount(0, $api->calls);
    }

    public function test_sends_with_no_confirm(): void
    {
        $this->setUpUserDatabase();
        config(['laragram.queue.enabled' => false]);

        $api = new RecordingBotAPI();
        $this->app->instance('laragram.api', $api);

        $this->makeUser();
        $this->makeUser();

        $this->artisan('laragram:broadcast', ['message' => 'hi', '--no-confirm' => true])
            ->expectsOutputToContain('Broadcast complete')
            ->assertSuccessful();

        $this->assertCount(2, $api->calls);
    }
}
