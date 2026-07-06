<?php

/*
 * Route fixture for ReactionsFlowTest — observing a message_reaction update
 * and reacting to an incoming message.
 */

use Wekser\Laragram\BotRequest;
use Wekser\Laragram\Facades\BotResponse;

$collection->get('message_reaction')
    ->call(fn (BotRequest $request) => [
        BotResponse::text('You reacted: ' . ($request->messageReaction()['new_reaction'][0]['emoji'] ?? '?'), null),
        BotResponse::react('❤️'),
    ]);

$collection->get('message')
    ->contains('/like')
    ->call(fn () => BotResponse::react('👍', big: true));
