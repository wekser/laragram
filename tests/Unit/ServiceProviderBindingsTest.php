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
}
