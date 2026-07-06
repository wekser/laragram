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

namespace Wekser\Laragram\Tests\Unit\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Wekser\Laragram\Services\Payments;
use Wekser\Laragram\Telegram\Payments\Invoice;

#[CoversClass(Payments::class)]
class PaymentsTest extends TestCase
{
    public function test_invoice_link_calls_create_invoice_link_and_returns_url(): void
    {
        $api = new PaymentsSpyBotAPI(['createInvoiceLink' => 'https://t.me/invoice/abc']);

        $link = (new Payments($api))->invoiceLink(
            Invoice::make()->title('Pro')->description('1 month')->payload('p')->stars(500)
        );

        $this->assertSame('createInvoiceLink', $api->calledMethod);
        $this->assertSame('https://t.me/invoice/abc', $link);
        $this->assertSame('XTR', $api->calledParams['currency']);
    }

    public function test_invoice_link_accepts_raw_array(): void
    {
        $api = new PaymentsSpyBotAPI(['createInvoiceLink' => 'https://t.me/invoice/xyz']);

        $link = (new Payments($api))->invoiceLink(['title' => 'Raw']);

        $this->assertSame('Raw', $api->calledParams['title']);
        $this->assertSame('https://t.me/invoice/xyz', $link);
    }

    public function test_refund_calls_refund_star_payment_with_correct_params(): void
    {
        $api = new PaymentsSpyBotAPI(['refundStarPayment' => true]);

        $ok = (new Payments($api))->refund(4242, 'charge_xyz');

        $this->assertTrue($ok);
        $this->assertSame('refundStarPayment', $api->calledMethod);
        $this->assertSame(4242, $api->calledParams['user_id']);
        $this->assertSame('charge_xyz', $api->calledParams['telegram_payment_charge_id']);
    }

    public function test_refund_returns_false_when_api_does_not_confirm(): void
    {
        $api = new PaymentsSpyBotAPI(['refundStarPayment' => false]);

        $this->assertFalse((new Payments($api))->refund(1, 'c'));
    }

    public function test_star_transactions_calls_get_star_transactions_with_paging(): void
    {
        $api = new PaymentsSpyBotAPI(['getStarTransactions' => ['transactions' => []]]);

        $result = (new Payments($api))->starTransactions(offset: 20, limit: 50);

        $this->assertSame('getStarTransactions', $api->calledMethod);
        $this->assertSame(20, $api->calledParams['offset']);
        $this->assertSame(50, $api->calledParams['limit']);
        $this->assertSame(['transactions' => []], $result);
    }
}

/**
 * Hand-written BotAPI spy — avoids PHPUnit's deprecated addMethods() API.
 * Named distinctly so it does not clash with the FakeBotAPI in MediaUploaderTest
 * (same namespace).
 */
class PaymentsSpyBotAPI extends \Wekser\Laragram\BotAPI
{
    public string $calledMethod = '';
    public array  $calledParams = [];

    /** @param array<string, mixed> $responses  method → fake Telegram result */
    public function __construct(private readonly array $responses = [])
    {
        // Skip parent constructor (token validation is irrelevant in tests).
    }

    public function __call(string $method, array $arguments): mixed
    {
        $this->calledMethod = $method;
        $this->calledParams = $arguments[0] ?? [];

        return $this->responses[$method] ?? [];
    }
}
