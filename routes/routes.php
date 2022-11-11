<?php

app('router')->group([
    'prefix' => config('laragram.env.prefix'),
    'middleware' => ['laragram.auth', 'laragram.hook']
], function ($router) {
    $router->post(config('laragram.env.secret'), '\Wekser\Laragram\Laragram@index');
});