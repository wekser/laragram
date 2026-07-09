<?php

/*
 * Route fixture for ForumTopicTest — exercises the ->thread() route constraint,
 * per-(user, chat, topic) station isolation, and outbound message_thread_id.
 */

use Wekser\Laragram\Facades\BotResponse;

// Restricted to forum topic 42; declared first so it wins over /start below.
$collection->get('message')
    ->contains('/start')
    ->thread(42)
    ->call(fn () => BotResponse::text('STARTED_IN_42')->redirect('step1'));

// /start from any station, any chat, any topic.
$collection->get('message')
    ->contains('/start')
    ->call(fn () => BotResponse::text('started')->redirect('step1'));

// Explicitly opt out of the topic the update came from.
$collection->get('message')
    ->contains('/general')
    ->call(fn () => BotResponse::text('TO_GENERAL')->thread(null));

// Push a notification into a fixed topic regardless of where the command ran.
$collection->get('message')
    ->contains('/notify')
    ->call(fn () => BotResponse::text('NOTIFIED')->thread(7));

// A method that takes no message_thread_id must never receive one.
$collection->get('callback_query')
    ->call(fn () => BotResponse::answer('ACKED'));

// Any message while the user is at 'step1' in THIS chat and topic.
$collection->get('message')
    ->from('step1')
    ->call(fn () => BotResponse::text('AT_STEP1'));

$collection->fallback()
    ->call(fn () => BotResponse::text('FALLBACK'));
