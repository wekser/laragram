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
use Wekser\Laragram\Telegram\Inline\InlineResults;
use Wekser\Laragram\Tests\TestCase;

/**
 * BotResponse::inlineResults() builds the answerInlineQuery payload, and
 * ResponseTransformer injects the inline_query_id (with no chat_id).
 */
#[CoversClass(BotResponse::class)]
#[CoversClass(ResponseTransformer::class)]
class InlineResponseTest extends TestCase
{
    private function response(): BotResponse
    {
        return new BotResponse(config('laragram.paths.views'));
    }

    public function test_inline_results_builds_answer_inline_query_payload(): void
    {
        $contents = $this->response()->inlineResults(
            InlineResults::make()->article('1', 'Hi', 'Hello!')->cache(300)
        )->contents;

        $this->assertSame('answerInlineQuery', $contents['method']);
        $this->assertSame(300, $contents['cache_time']);
        $this->assertCount(1, $contents['results']);
        $this->assertSame('article', $contents['results'][0]['type']);
    }

    public function test_inline_results_accepts_raw_array(): void
    {
        $contents = $this->response()->inlineResults(['results' => []])->contents;

        $this->assertSame('answerInlineQuery', $contents['method']);
        $this->assertSame([], $contents['results']);
    }

    public function test_inline_results_are_distinct_instances(): void
    {
        $shared = $this->response();
        $a = $shared->inlineResults(InlineResults::make()->article('1', 'A', 'a'));
        $b = $shared->inlineResults(InlineResults::make()->article('2', 'B', 'b'));

        $this->assertNotSame($a, $b);
        $this->assertSame('1', $a->contents['results'][0]['id']);
        $this->assertSame('2', $b->contents['results'][0]['id']);
    }

    public function test_inline_query_id_is_injected_without_chat_id(): void
    {
        $request = new BotRequest([
            'update' => ['id' => 1, 'object' => ['id' => 'IQ777', 'query' => 'cats']],
            'route'  => [
                'event'    => 'inline_query',
                'listener' => 'query',
                'contains' => null,
                'uses'     => 'callback',
                'form'     => 'home',
            ],
            'data'   => ['query' => 'cats', 'all' => []],
        ]);

        $output = (new ResponseTransformer())->getResponse(
            $request,
            $this->response()->inlineResults(InlineResults::make()->article('1', 'A', 'a')),
        );

        $view = $output['response']['views'][0];

        $this->assertSame('IQ777', $view['inline_query_id']);
        $this->assertArrayNotHasKey('chat_id', $view);
    }
}
