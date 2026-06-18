<?php

/*
 * Stub bot routes used by BotRouteCollectionTest and BotRouterTest.
 *
 * $collection is injected by BotRouteCollection::loadRoutesFromFile().
 */

// Route 0: /start command — matches from any station, no station required
$collection->get('message')
    ->contains('/start')
    ->call(fn () => null);

// Route 1: any message from 'waiting_input' station, no text requirement
$collection->get('message')
    ->from('waiting_input')
    ->call(fn () => null);

// Route 2: 'Hello' text from 'menu_shown' station (separate station to avoid conflicts)
$collection->get('message')
    ->from('menu_shown')
    ->contains('Hello')
    ->call(fn () => null);

// Route 3: any callback_query — no station, no text requirement
$collection->get('callback_query')
    ->call(fn () => null);

// Route 4: /admin command — admin role only, no station requirement
$collection->get('message')
    ->contains('/admin')
    ->role('admin')
    ->call(fn () => null);

// Route 5: any message from 'admin_panel' station — admin role only
$collection->get('message')
    ->from('admin_panel')
    ->role('admin')
    ->call(fn () => null);
