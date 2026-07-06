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
use PHPUnit\Framework\TestCase;
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\Support\IncomingFile;

#[CoversClass(BotRequest::class)]
#[CoversClass(IncomingFile::class)]
class BotRequestFileTest extends TestCase
{
    private function request(array $object): BotRequest
    {
        return new BotRequest(['update' => ['object' => $object]]);
    }

    public function test_file_id_from_document(): void
    {
        $request = $this->request(['document' => ['file_id' => 'DOC1', 'file_name' => 'a.pdf']]);

        $this->assertSame('DOC1', $request->fileId());
    }

    public function test_file_id_from_photo_uses_largest_size(): void
    {
        $request = $this->request(['photo' => [
            ['file_id' => 'small',  'width' => 90],
            ['file_id' => 'medium', 'width' => 320],
            ['file_id' => 'large',  'width' => 800],
        ]]);

        $this->assertSame('large', $request->fileId());
    }

    public function test_file_id_from_voice(): void
    {
        $request = $this->request(['voice' => ['file_id' => 'VOICE1', 'duration' => 3]]);

        $this->assertSame('VOICE1', $request->fileId());
    }

    public function test_file_id_is_null_when_no_file_present(): void
    {
        $request = $this->request(['text' => 'just text']);

        $this->assertNull($request->fileId());
    }

    public function test_file_returns_incoming_file_handle(): void
    {
        $request = $this->request(['document' => ['file_id' => 'DOC1']]);

        $file = $request->file();

        $this->assertInstanceOf(IncomingFile::class, $file);
        $this->assertSame('DOC1', $file->id());
    }

    public function test_file_returns_null_when_no_file_present(): void
    {
        $this->assertNull($this->request(['text' => 'hi'])->file());
    }
}
