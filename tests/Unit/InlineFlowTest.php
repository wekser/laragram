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
 * End-to-end test of the inline-mode flow through InteractsWithBot: an
 * inline_query is answered with answerInlineQuery, and a chosen_inline_result
 * routes to its handler.
 */
class InlineFlowTest extends TestCase
{
    use InteractsWithBot;

    protected function setUp(): void
    {
        parent::setUp();

        config(['laragram.paths.route' => 'inline']);
        Router::flushCache();
        BotUpdateFactory::reset();
    }

    protected function tearDown(): void
    {
        Router::flushCache();
        BotUpdateFactory::reset();
        ComponentContext::reset();

        parent::tearDown();
    }

    public function test_inline_query_is_answered(): void
    {
        $this->botReceives(BotUpdateFactory::inlineQuery('hello'));

        $this->assertBotRepliedWith('answerInlineQuery');
    }

    public function test_answer_carries_the_inline_query_id(): void
    {
        $update = BotUpdateFactory::inlineQuery('hello');
        $this->botReceives($update);

        $this->assertResponseContains('inline_query_id', $update['inline_query']['id']);
    }

    public function test_answer_carries_results(): void
    {
        $this->botReceives(BotUpdateFactory::inlineQuery('hello'));

        $results = $this->getBotResponse()['results'] ?? [];

        $this->assertCount(1, $results);
        $this->assertSame('Say hello', $results[0]['title']);
    }

    public function test_chosen_inline_result_routes_to_handler(): void
    {
        $this->botReceives(BotUpdateFactory::chosenInlineResult(resultId: '1'));

        $this->assertBotRepliedWith('sendMessage');
        $this->assertBotRepliedText('Thanks for picking');
    }
}
