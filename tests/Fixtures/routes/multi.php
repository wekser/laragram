<?php

/*
 * Route fixture for MultiResponseTest — a controller returning several
 * BotResponse objects via the facade (which resolves a shared singleton),
 * proving the multi-message reply flow end-to-end.
 */

use Wekser\Laragram\Facades\BotResponse;

$collection->get('message')
    ->contains('/multi')
    ->call(fn () => [
        BotResponse::text('First message'),
        BotResponse::text('Second message')->redirect('done'),
    ]);
