<?php

app('router')->group([
    'prefix' => config('laragram.bot.prefix'),
    'middleware' => ['laragram.auth', 'laragram.hook']
], function ($router) {
    $router->post(config('laragram.bot.secret'), '\Wekser\Laragram\Laragram@index');
});