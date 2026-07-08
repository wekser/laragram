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

namespace Wekser\Laragram\Tests\Unit\Broadcasting;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Broadcasting\ViewCatalog;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(ViewCatalog::class)]
class ViewCatalogTest extends TestCase
{
    public function test_lists_directories_that_contain_a_renderable_component(): void
    {
        $views = ViewCatalog::all();

        // Every fixture dir carries a text.php or media component, so all qualify.
        $this->assertContains('broadcast_view', $views);
        $this->assertContains('photo_view', $views);
        $this->assertContains('media_view', $views);
    }

    public function test_result_is_sorted_alphabetically(): void
    {
        $views = ViewCatalog::all();

        $sorted = $views;
        sort($sorted);

        $this->assertSame($sorted, $views);
    }

    public function test_returns_empty_array_when_base_directory_is_missing(): void
    {
        config(['laragram.paths.views' => 'does-not-exist']);

        $this->assertSame([], ViewCatalog::all());
    }
}
