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

namespace Wekser\Laragram\Tests\Unit;

use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Testing\InteractsWithBot;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;

/**
 * End-to-end test of the multi-message reply flow through InteractsWithBot:
 * a controller returns an array of BotResponse, and the dispatcher delivers
 * each as a separate Bot API call (captured by the recording transport).
 */
class MultiResponseTest extends TestCase
{
    use InteractsWithBot;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the dedicated multi-response route fixture.
        config(['laragram.paths.route' => 'multi']);
        Router::flushCache();
        BotUpdateFactory::reset();
    }

    protected function tearDown(): void
    {
        // Clear the static route cache so other tests reload the default routes.
        Router::flushCache();
        BotUpdateFactory::reset();
        ComponentContext::reset();

        parent::tearDown();
    }

    public function test_controller_returning_array_sends_multiple_messages(): void
    {
        $this->botReceives(BotUpdateFactory::message('/multi'));

        $this->assertBotRepliedTimes(2);
        $this->assertNthReplyWith(0, 'sendMessage');
        $this->assertNthReplyWith(1, 'sendMessage');
        $this->assertNthReplyText(0, 'First message');
        $this->assertNthReplyText(1, 'Second message');
    }

    public function test_multi_response_redirect_uses_last_write_wins(): void
    {
        $this->botReceives(BotUpdateFactory::message('/multi'));

        $this->assertUserRedirectedTo('done');
    }

    public function test_each_message_is_a_distinct_payload(): void
    {
        // Guards the BotResponse clone-on-entry fix: two facade calls must not
        // collapse into the same singleton payload.
        $this->botReceives(BotUpdateFactory::message('/multi'));

        $messages = $this->getBotResponses();

        $this->assertNotSame(
            $messages[0]['text'],
            $messages[1]['text'],
            'Both messages share the same text — the facade singleton was not cloned per response.'
        );
    }
}
