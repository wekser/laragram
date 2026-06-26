<?php

/*
 * Route fixture for SceneFlowTest — a normal route whose handler begins a scene
 * by returning a SceneTransition, exercising the Router → SceneManager::start()
 * hand-off end-to-end through InteractsWithBot.
 */

use Wekser\Laragram\Tests\Fixtures\OrderSceneController;

$collection->get('message')
    ->contains('/order')
    ->call([OrderSceneController::class, 'start']);
