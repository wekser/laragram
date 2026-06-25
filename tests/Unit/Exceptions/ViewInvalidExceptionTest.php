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

namespace Wekser\Laragram\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Wekser\Laragram\Exceptions\ViewInvalidException;

#[CoversClass(ViewInvalidException::class)]
class ViewInvalidExceptionTest extends TestCase
{
    public function test_message_includes_the_view_path(): void
    {
        $e = new ViewInvalidException('home.greeting');

        $this->assertStringContainsString('home.greeting', $e->getMessage());
    }

    public function test_preserves_the_previous_exception_and_code(): void
    {
        $root = new RuntimeException('boom');

        $e = new ViewInvalidException('/path/to/view', 7, $root);

        // Regression: the old constructor only accepted ($view) and silently
        // dropped the code + previous exception, hiding the render root cause.
        $this->assertSame($root, $e->getPrevious());
        $this->assertSame(7, $e->getCode());
    }
}
