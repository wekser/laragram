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

namespace Wekser\Laragram\Tests\Unit\Telegram\Payments;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Telegram\Payments\Invoice;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(Invoice::class)]
class InvoiceTest extends TestCase
{
    private function base(): Invoice
    {
        return Invoice::make()
            ->title('Pro subscription')
            ->description('One month of Pro')
            ->payload('sub_pro_42');
    }

    // -------------------------------------------------------------------------
    // Telegram Stars
    // -------------------------------------------------------------------------

    public function test_stars_invoice_builds_expected_params(): void
    {
        $params = $this->base()->stars(500, 'Pro access')->toArray();

        $this->assertSame('XTR', $params['currency']);
        $this->assertSame('', $params['provider_token']);
        $this->assertSame([['label' => 'Pro access', 'amount' => 500]], $params['prices']);
        $this->assertSame('Pro subscription', $params['title']);
        $this->assertSame('sub_pro_42', $params['payload']);
    }

    public function test_stars_default_label(): void
    {
        $params = $this->base()->stars(100)->toArray();

        $this->assertSame('Total', $params['prices'][0]['label']);
    }

    public function test_stars_invoice_rejects_multiple_price_lines(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/exactly one price line/');

        $this->base()->stars(100)->price('Extra', 50)->toArray();
    }

    // -------------------------------------------------------------------------
    // Fiat
    // -------------------------------------------------------------------------

    public function test_fiat_invoice_builds_expected_params(): void
    {
        $params = $this->base()
            ->currency('USD')
            ->price('Pizza', 1998)
            ->price('Delivery', 300)
            ->providerToken('PROVIDER:TOKEN')
            ->needShippingAddress()
            ->flexible()
            ->toArray();

        $this->assertSame('USD', $params['currency']);
        $this->assertSame('PROVIDER:TOKEN', $params['provider_token']);
        $this->assertCount(2, $params['prices']);
        $this->assertTrue($params['need_shipping_address']);
        $this->assertTrue($params['is_flexible']);
    }

    public function test_fiat_invoice_falls_back_to_config_defaults(): void
    {
        config([
            'laragram.payments.currency'       => 'EUR',
            'laragram.payments.provider_token' => 'CONFIG:TOKEN',
        ]);

        $params = $this->base()->price('Item', 500)->toArray();

        $this->assertSame('EUR', $params['currency']);
        $this->assertSame('CONFIG:TOKEN', $params['provider_token']);
    }

    public function test_fiat_invoice_without_provider_token_throws(): void
    {
        config(['laragram.payments.provider_token' => '']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/provider_token is required/');

        $this->base()->currency('USD')->price('Item', 500)->toArray();
    }

    // -------------------------------------------------------------------------
    // Optional fields
    // -------------------------------------------------------------------------

    public function test_optional_fields_are_mapped_to_api_names(): void
    {
        $params = $this->base()
            ->stars(500)
            ->photo('https://example.com/p.jpg', 600, 400)
            ->maxTip(1000)
            ->suggestedTips([100, 200, 500])
            ->startParameter('deep-link')
            ->providerData(['foo' => 'bar'])
            ->needName()
            ->toArray();

        $this->assertSame('https://example.com/p.jpg', $params['photo_url']);
        $this->assertSame(600, $params['photo_width']);
        $this->assertSame(400, $params['photo_height']);
        $this->assertSame(1000, $params['max_tip_amount']);
        $this->assertSame([100, 200, 500], $params['suggested_tip_amounts']);
        $this->assertSame('deep-link', $params['start_parameter']);
        $this->assertSame('{"foo":"bar"}', $params['provider_data']);
        $this->assertTrue($params['need_name']);
    }

    public function test_falsey_optional_flags_are_omitted(): void
    {
        $params = $this->base()->stars(500)->toArray();

        $this->assertArrayNotHasKey('need_name', $params);
        $this->assertArrayNotHasKey('is_flexible', $params);
        $this->assertArrayNotHasKey('max_tip_amount', $params);
        $this->assertArrayNotHasKey('suggested_tip_amounts', $params);
    }

    // -------------------------------------------------------------------------
    // Required-field validation
    // -------------------------------------------------------------------------

    public function test_missing_title_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/title is required/');

        Invoice::make()->description('d')->payload('p')->stars(100)->toArray();
    }

    public function test_missing_description_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/description is required/');

        Invoice::make()->title('t')->payload('p')->stars(100)->toArray();
    }

    public function test_missing_payload_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/payload is required/');

        Invoice::make()->title('t')->description('d')->stars(100)->toArray();
    }

    public function test_missing_prices_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/at least one price line/');

        $this->base()->currency('USD')->providerToken('X')->toArray();
    }
}
