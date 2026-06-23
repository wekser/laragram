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

namespace Wekser\Laragram\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\BotRequest;
use Wekser\Laragram\BotResponse;
use Wekser\Laragram\Exceptions\ResponseInvalidException;
use Wekser\Laragram\Http\ResponseTransformer;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(ResponseTransformer::class)]
class ResponseTransformerTest extends TestCase
{
    private function request(): BotRequest
    {
        return new BotRequest([
            'update' => ['id' => 1, 'object' => ['chat' => ['id' => 99]]],
            'route'  => [
                'event'    => 'message',
                'listener' => 'text',
                'contains' => null,
                'uses'     => 'callback',
                'form'     => 'home',
            ],
            'data'   => ['query' => null, 'all' => []],
        ]);
    }

    private function response(): BotResponse
    {
        // BotAuth::user() is stubbed to null in TestCase::setUp().
        return new BotResponse(config('laragram.paths.views'));
    }

    public function test_single_response_produces_one_view(): void
    {
        $output = (new ResponseTransformer())->getResponse(
            $this->request(),
            $this->response()->text('Hello'),
        );

        $this->assertCount(1, $output['response']['views']);
        $this->assertSame('sendMessage', $output['response']['views'][0]['method']);
        $this->assertSame(99, $output['response']['views'][0]['chat_id']);
    }

    public function test_array_of_responses_produces_views_in_order(): void
    {
        $output = (new ResponseTransformer())->getResponse($this->request(), [
            $this->response()->text('one'),
            $this->response()->photo('file_id'),
            $this->response()->text('three'),
        ]);

        $methods = array_column($output['response']['views'], 'method');

        $this->assertSame(['sendMessage', 'sendPhoto', 'sendMessage'], $methods);
    }

    public function test_redirect_uses_last_response_that_sets_it(): void
    {
        $output = (new ResponseTransformer())->getResponse($this->request(), [
            $this->response()->text('one')->redirect('first'),
            $this->response()->text('two')->redirect('second'),
            $this->response()->text('three'), // sets no station
        ]);

        $this->assertSame('second', $output['response']['redirect']);
    }

    public function test_redirect_falls_back_to_route_form_when_none_set(): void
    {
        $output = (new ResponseTransformer())->getResponse($this->request(), [
            $this->response()->text('one'),
            $this->response()->text('two'),
        ]);

        $this->assertSame('home', $output['response']['redirect']);
    }

    public function test_string_response_is_supported(): void
    {
        $output = (new ResponseTransformer())->getResponse($this->request(), 'plain text');

        $this->assertCount(1, $output['response']['views']);
        $this->assertSame('sendMessage', $output['response']['views'][0]['method']);
        $this->assertSame('plain text', $output['response']['views'][0]['text']);
    }

    public function test_null_response_returns_null(): void
    {
        $this->assertNull((new ResponseTransformer())->getResponse($this->request(), null));
    }

    public function test_empty_array_returns_null(): void
    {
        $this->assertNull((new ResponseTransformer())->getResponse($this->request(), []));
    }

    public function test_array_with_null_items_skips_them(): void
    {
        $output = (new ResponseTransformer())->getResponse($this->request(), [
            null,
            $this->response()->text('only one'),
            null,
        ]);

        $this->assertCount(1, $output['response']['views']);
    }

    public function test_invalid_item_throws(): void
    {
        $this->expectException(ResponseInvalidException::class);

        (new ResponseTransformer())->getResponse($this->request(), [
            $this->response()->text('ok'),
            42, // not a BotResponse|string
        ]);
    }

    public function test_raw_payload_array_is_rejected_not_iterated(): void
    {
        // A bare associative payload (not a list of responses) must be treated as
        // a single unsupported response and rejected — NOT foreach-iterated into
        // one bogus message per scalar value.
        $this->expectException(ResponseInvalidException::class);

        (new ResponseTransformer())->getResponse($this->request(), [
            'method' => 'sendMessage',
            'text'   => 'hi',
        ]);
    }

    public function test_list_of_strings_is_treated_as_a_batch(): void
    {
        $output = (new ResponseTransformer())->getResponse($this->request(), ['one', 'two']);

        $this->assertCount(2, $output['response']['views']);
        $this->assertSame('one', $output['response']['views'][0]['text']);
        $this->assertSame('two', $output['response']['views'][1]['text']);
    }
}
