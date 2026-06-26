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

namespace Wekser\Laragram\Tests\Unit\Listeners;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Exceptions\BotBlockedException;
use Wekser\Laragram\Exceptions\ChatNotFoundException;
use Wekser\Laragram\Exceptions\ExceptionHandler;
use Wekser\Laragram\Listeners\DeactivateUnreachableUser;
use Wekser\Laragram\Tests\Concerns\UsesUserDatabase;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(DeactivateUnreachableUser::class)]
class DeactivateUnreachableUserTest extends TestCase
{
    use UsesUserDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpUserDatabase();
        config(['laragram.broadcast.deactivate_unreachable' => true]);
    }

    public function test_terminal_block_deactivates_the_matching_user(): void
    {
        $user = $this->makeUser();

        ExceptionHandler::handle(new BotBlockedException($user->uid));

        $this->assertFalse($user->fresh()->isActive());
        $this->assertNotNull($user->fresh()->deactivated_at);
    }

    public function test_chat_not_found_deactivates_by_chat_id(): void
    {
        $user = $this->makeUser();

        ExceptionHandler::handle(new ChatNotFoundException((string) $user->uid));

        $this->assertFalse($user->fresh()->isActive());
    }

    public function test_zero_uid_does_not_deactivate_anyone(): void
    {
        // A missing error context yields id 0 (the historical bug); the guard
        // must treat it as "no user" rather than querying where('uid', 0).
        $user = $this->makeUser();

        ExceptionHandler::handle(new BotBlockedException(0));

        $this->assertTrue($user->fresh()->isActive());
    }

    public function test_negative_chat_id_does_not_deactivate_a_user(): void
    {
        // A failed send to a group/channel carries a negative chat id, which is
        // never a private-chat uid and must not deactivate a matching user.
        $user = $this->makeUser();

        ExceptionHandler::handle(new ChatNotFoundException('-100123456789'));

        $this->assertTrue($user->fresh()->isActive());
    }

    public function test_non_terminal_exception_does_not_deactivate(): void
    {
        $user = $this->makeUser();

        ExceptionHandler::handle(new \RuntimeException('boom'));

        $this->assertTrue($user->fresh()->isActive());
    }

    public function test_respects_disabling_config(): void
    {
        config(['laragram.broadcast.deactivate_unreachable' => false]);

        $user = $this->makeUser();

        ExceptionHandler::handle(new BotBlockedException($user->uid));

        $this->assertTrue($user->fresh()->isActive());
    }
}
