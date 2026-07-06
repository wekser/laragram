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

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Http\ResponseTransformer;
use Wekser\Laragram\Telegram\Payments\Invoice;
use Wekser\Laragram\Tests\TestCase;

/**
 * The BotResponse payment helpers build the right Telegram payloads, and
 * ResponseTransformer injects the pre_checkout / shipping query ids.
 */
#[CoversClass(BotResponse::class)]
#[CoversClass(ResponseTransformer::class)]
class PaymentsResponseTest extends TestCase
{
    private function response(): BotResponse
    {
        return new BotResponse(config('laragram.paths.views'));
    }

    // -------------------------------------------------------------------------
    // BotResponse payloads
    // -------------------------------------------------------------------------

    public function test_invoice_builds_send_invoice_payload(): void
    {
        $contents = $this->response()->invoice(
            Invoice::make()->title('Pro')->description('1 month')->payload('p')->stars(500)
        )->contents;

        $this->assertSame('sendInvoice', $contents['method']);
        $this->assertSame('Pro', $contents['title']);
        $this->assertSame('XTR', $contents['currency']);
    }

    public function test_invoice_accepts_raw_array(): void
    {
        $contents = $this->response()->invoice(['title' => 'Raw', 'currency' => 'USD'])->contents;

        $this->assertSame('sendInvoice', $contents['method']);
        $this->assertSame('Raw', $contents['title']);
    }

    public function test_approve_checkout_payload(): void
    {
        $contents = $this->response()->approveCheckout()->contents;

        $this->assertSame('answerPreCheckoutQuery', $contents['method']);
        $this->assertTrue($contents['ok']);
    }

    public function test_decline_checkout_payload(): void
    {
        $contents = $this->response()->declineCheckout('Out of stock')->contents;

        $this->assertSame('answerPreCheckoutQuery', $contents['method']);
        $this->assertFalse($contents['ok']);
        $this->assertSame('Out of stock', $contents['error_message']);
    }

    public function test_approve_shipping_payload(): void
    {
        $options  = [['id' => 'std', 'title' => 'Standard', 'prices' => [['label' => 'Ship', 'amount' => 500]]]];
        $contents = $this->response()->approveShipping($options)->contents;

        $this->assertSame('answerShippingQuery', $contents['method']);
        $this->assertTrue($contents['ok']);
        $this->assertSame($options, $contents['shipping_options']);
    }

    public function test_decline_shipping_payload(): void
    {
        $contents = $this->response()->declineShipping('No delivery here')->contents;

        $this->assertSame('answerShippingQuery', $contents['method']);
        $this->assertFalse($contents['ok']);
        $this->assertSame('No delivery here', $contents['error_message']);
    }

    public function test_payment_helpers_return_distinct_instances(): void
    {
        // clone-on-entry: two facade-style calls must not collapse into one payload.
        $shared = $this->response();
        $a = $shared->approveCheckout();
        $b = $shared->declineCheckout('nope');

        $this->assertNotSame($a, $b);
        $this->assertTrue($a->contents['ok']);
        $this->assertFalse($b->contents['ok']);
    }

    // -------------------------------------------------------------------------
    // ResponseTransformer id injection
    // -------------------------------------------------------------------------

    private function request(string $event, array $object): BotRequest
    {
        return new BotRequest([
            'update' => ['id' => 1, 'object' => $object],
            'route'  => [
                'event'    => $event,
                'listener' => 'invoice_payload',
                'contains' => null,
                'uses'     => 'callback',
                'form'     => 'home',
            ],
            'data'   => ['query' => null, 'all' => []],
        ]);
    }

    public function test_pre_checkout_query_id_is_injected(): void
    {
        $output = (new ResponseTransformer())->getResponse(
            $this->request('pre_checkout_query', ['id' => 'PCQ42', 'invoice_payload' => 'p']),
            $this->response()->approveCheckout(),
        );

        $view = $output['response']['views'][0];

        $this->assertSame('PCQ42', $view['pre_checkout_query_id']);
        // answer* query methods carry no chat_id.
        $this->assertArrayNotHasKey('chat_id', $view);
    }

    public function test_shipping_query_id_is_injected(): void
    {
        $output = (new ResponseTransformer())->getResponse(
            $this->request('shipping_query', ['id' => 'SQ99', 'invoice_payload' => 'p']),
            $this->response()->approveShipping([]),
        );

        $view = $output['response']['views'][0];

        $this->assertSame('SQ99', $view['shipping_query_id']);
        $this->assertArrayNotHasKey('chat_id', $view);
    }
}
