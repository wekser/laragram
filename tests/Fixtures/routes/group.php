<?php

/*
 * Route fixture for GroupChatTest — exercises command matching in groups
 * (the "@botusername" suffix), per-(user,chat) station isolation, and the
 * chat-type route filters (->inGroups() / ->inPrivate()).
 */

use Wekser\Laragram\Facades\BotResponse;

// /start from any station → advance to the 'step1' station.
$collection->get('message')
    ->contains('/start')
    ->call(fn () => BotResponse::text('started')->redirect('step1'));

// Any message while the user is at 'step1' in THIS chat.
$collection->get('message')
    ->from('step1')
    ->call(fn () => BotResponse::text('AT_STEP1'));

// Group-only command.
$collection->get('message')
    ->contains('/groupcmd')
    ->inGroups()
    ->call(fn () => BotResponse::text('GROUP_ONLY'));

// Private-only command.
$collection->get('message')
    ->contains('/privatecmd')
    ->inPrivate()
    ->call(fn () => BotResponse::text('PRIVATE_ONLY'));

// Catch-all so every other update produces an observable reply.
$collection->fallback()
    ->call(fn () => BotResponse::text('FALLBACK'));
