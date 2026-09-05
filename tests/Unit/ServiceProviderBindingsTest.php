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
use Wekser\Laragram\BotClient;
use Wekser\Laragram\Providers\LaragramServiceProvider;
use Wekser\Laragram\Services\MediaDownloader;
use Wekser\Laragram\Services\MediaUploader;
use Wekser\Laragram\Services\Payments;
use Wekser\Laragram\Tests\TestCase;

/**
 * Regression: the "laragram.media" / "laragram.downloader" / "laragram.payments"
 * aliases must resolve to their services. A bug bound the singletons under the
 * FQCN while aliasing the class name TO the string, leaving the string abstract
 * unbound — so IncomingFile::save() (app('laragram.downloader')) threw
 * "Target class [laragram.downloader] does not exist".
 */
#[CoversClass(LaragramServiceProvider::class)]
class ServiceProviderBindingsTest extends TestCase
{
    public function test_media_aliases_resolve_both_ways(): void
    {
        $this->assertInstanceOf(MediaUploader::class, app('laragram.media'));
        $this->assertInstanceOf(MediaUploader::class, app(MediaUploader::class));
        $this->assertSame(app('laragram.media'), app(MediaUploader::class));
    }

    public function test_downloader_aliases_resolve_both_ways(): void
    {
        $this->assertInstanceOf(MediaDownloader::class, app('laragram.downloader'));
        $this->assertInstanceOf(MediaDownloader::class, app(MediaDownloader::class));
        $this->assertSame(app('laragram.downloader'), app(MediaDownloader::class));
    }

    public function test_payments_aliases_resolve_both_ways(): void
    {
        $this->assertInstanceOf(Payments::class, app('laragram.payments'));
        $this->assertInstanceOf(Payments::class, app(Payments::class));
        $this->assertSame(app('laragram.payments'), app(Payments::class));
    }

    /**
     * The transport knobs under config('laragram.telegram') must reach the
     * BotClient the API singleton wraps — without this wiring a host app has no
     * way to raise a timeout, enable retries or pin the IP version.
     */
    public function test_transport_config_reaches_the_bot_client(): void
    {
        config([
            'laragram.telegram.timeout'         => 45,
            'laragram.telegram.connect_timeout' => 20,
            'laragram.telegram.retries'         => 3,
            'laragram.telegram.retry_delay'     => 500,
            'laragram.telegram.ip_version'      => 4,
            'laragram.telegram.proxy'           => 'http://proxy.local:3128',
        ]);

        $client = app('laragram.api')->getClient();

        $this->assertSame(45, $this->clientProperty($client, 'timeout'));
        $this->assertSame(20, $this->clientProperty($client, 'connectTimeout'));
        $this->assertSame(3, $this->clientProperty($client, 'retries'));
        $this->assertSame(500, $this->clientProperty($client, 'retryDelay'));
        $this->assertSame(4, $this->clientProperty($client, 'ipVersion'));
        $this->assertSame('http://proxy.local:3128', $this->clientProperty($client, 'proxy'));
    }

    public function test_transport_config_falls_back_to_client_defaults(): void
    {
        config([
            'laragram.telegram.timeout'         => null,
            'laragram.telegram.connect_timeout' => null,
            'laragram.telegram.retries'         => null,
            'laragram.telegram.retry_delay'     => null,
            'laragram.telegram.ip_version'      => null,
            'laragram.telegram.proxy'           => null,
        ]);

        $client = app('laragram.api')->getClient();

        $this->assertSame(30, $this->clientProperty($client, 'timeout'));
        $this->assertSame(10, $this->clientProperty($client, 'connectTimeout'));
        $this->assertSame(2, $this->clientProperty($client, 'retries'));
        $this->assertSame(300, $this->clientProperty($client, 'retryDelay'));
        $this->assertNull($this->clientProperty($client, 'ipVersion'));
        $this->assertNull($this->clientProperty($client, 'proxy'));
    }

    /**
     * These values come from env(), where a bare "LARAGRAM_API_TIMEOUT=" line
     * yields an empty string, not null. Casting that to 0 and handing it to a
     * setter would throw out of the singleton closure and take down every
     * outbound call — over what reads like a commented-out setting.
     */
    public function test_blank_transport_config_values_are_treated_as_unset(): void
    {
        config([
            'laragram.telegram.timeout'         => '',
            'laragram.telegram.connect_timeout' => '',
            'laragram.telegram.retries'         => '',
            'laragram.telegram.retry_delay'     => '',
            'laragram.telegram.ip_version'      => '',
            'laragram.telegram.proxy'           => '',
        ]);

        $client = app('laragram.api')->getClient();

        $this->assertSame(30, $this->clientProperty($client, 'timeout'));
        $this->assertSame(10, $this->clientProperty($client, 'connectTimeout'));
        $this->assertSame(2, $this->clientProperty($client, 'retries'));
        $this->assertSame(300, $this->clientProperty($client, 'retryDelay'));
        $this->assertNull($this->clientProperty($client, 'ipVersion'));
        $this->assertNull($this->clientProperty($client, 'proxy'));
    }

    private function clientProperty(BotClient $client, string $name): mixed
    {
        return (new \ReflectionProperty(BotClient::class, $name))->getValue($client);
    }
}
