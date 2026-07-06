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

namespace Wekser\Laragram\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wekser\Laragram\Support\Command;

#[CoversClass(Command::class)]
class CommandTest extends TestCase
{
    public function test_returns_token_unchanged_without_mention(): void
    {
        $this->assertSame('/start', Command::stripMention('/start'));
        $this->assertSame('/start', Command::stripMention('/start', 'MyBot'));
    }

    public function test_strips_matching_mention_when_username_configured(): void
    {
        $this->assertSame('/start', Command::stripMention('/start@MyBot', 'MyBot'));
    }

    public function test_mention_match_is_case_insensitive_and_ignores_leading_at(): void
    {
        $this->assertSame('/start', Command::stripMention('/start@mybot', 'MyBot'));
        $this->assertSame('/start', Command::stripMention('/start@MyBot', '@MyBot'));
    }

    public function test_leaves_other_bots_mention_untouched_when_username_configured(): void
    {
        $this->assertSame('/start@OtherBot', Command::stripMention('/start@OtherBot', 'MyBot'));
    }

    public function test_strips_any_mention_when_username_empty(): void
    {
        $this->assertSame('/start', Command::stripMention('/start@AnyBot'));
        $this->assertSame('/start', Command::stripMention('/start@AnyBot', ''));
    }
}
