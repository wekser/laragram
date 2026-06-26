<?php

/*
 * Route fixture: a command handler that begins a scene. Used to verify a global
 * escape command can leave one scene and start another (the escaped-to handler
 * returns a SceneTransition, so escape() must keep the new scene's state).
 */

use Wekser\Laragram\Tests\Fixtures\OrderSceneController;

$collection->get('message')
    ->contains('/enter')
    ->call([OrderSceneController::class, 'start']);
