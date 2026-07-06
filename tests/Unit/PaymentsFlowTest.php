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
 * End-to-end test of the incoming payment lifecycle through InteractsWithBot:
 * a pre_checkout_query is confirmed, and a successful_payment message routes to
 * a completion handler.
 */
class PaymentsFlowTest extends TestCase
{
    use InteractsWithBot;

    protected function setUp(): void
    {
        parent::setUp();

        config(['laragram.paths.route' => 'payments']);
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

    public function test_pre_checkout_query_is_approved(): void
    {
        $this->botReceives(BotUpdateFactory::preCheckoutQuery(payload: 'sub_pro_42'));

        $this->assertBotRepliedWith('answerPreCheckoutQuery');
        $this->assertResponseContains('ok', true);
    }

    public function test_pre_checkout_reply_carries_the_query_id(): void
    {
        $update = BotUpdateFactory::preCheckoutQuery();
        $this->botReceives($update);

        $this->assertResponseContains(
            'pre_checkout_query_id',
            $update['pre_checkout_query']['id'],
        );
    }

    public function test_successful_payment_message_routes_to_handler(): void
    {
        $this->botReceives(BotUpdateFactory::successfulPaymentMessage());

        $this->assertBotRepliedWith('sendMessage');
        $this->assertBotRepliedText('Payment received');
        $this->assertUserRedirectedTo('home');
    }
}
