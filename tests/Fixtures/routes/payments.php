<?php

/*
 * Route fixture for PaymentsFlowTest — the incoming half of the Telegram
 * payment lifecycle: confirming a pre_checkout_query and handling the
 * successful_payment carried by a message update.
 */

use Wekser\Laragram\Facades\BotResponse;

$collection->get('pre_checkout_query')
    ->call(fn () => BotResponse::approveCheckout());

$collection->get('message', 'successful_payment')
    ->call(fn () => BotResponse::text('Payment received, thank you!')->redirect('home'));
