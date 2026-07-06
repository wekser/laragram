<?php

/*
 * Route fixture for InlineFlowTest — answering an inline_query with a result
 * set, and observing a chosen_inline_result.
 */

use Wekser\Laragram\Facades\BotResponse;
use Wekser\Laragram\Telegram\Inline\InlineResults;

$collection->get('inline_query')
    ->call(fn () => BotResponse::inlineResults(
        InlineResults::make()
            ->article('1', 'Say hello', 'Hello there!')
            ->cache(0)
    ));

$collection->get('chosen_inline_result')
    ->call(fn () => BotResponse::text('Thanks for picking a result!'));
