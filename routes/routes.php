<?php

app('router')->group([
    'prefix' => config('laragram.telegram.prefix'),
    'middleware' => ['laragram.verify', 'laragram.auth', 'laragram.hook', 'laragram.throttle']
], function ($router) {
    $router->post(config('laragram.telegram.secret'), [\Wekser\Laragram\Laragram::class, 'index']);
});