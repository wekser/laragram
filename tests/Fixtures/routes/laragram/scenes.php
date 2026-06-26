<?php

/*
 * Scene fixtures for the Scene test suite.
 *
 * 'order'  — two steps with validation, transform, a [Controller, method]
 *            onComplete handler and a closure onCancel handler.
 * 'survey' — single step whose question is rendered from a view (string prompt).
 */

use Wekser\Laragram\Facades\BotResponse;
use Wekser\Laragram\Facades\BotScene;
use Wekser\Laragram\Tests\Fixtures\OrderSceneController;

BotScene::define('order')
    ->step('size')
        ->ask(fn ($ctx) => BotResponse::text('Choose a size'))
        ->rules(['required', 'in:S,M,L'])
    ->step('color')
        ->ask(fn ($ctx) => BotResponse::text('Choose a color for size ' . $ctx->get('size')))
        ->rules(['required', 'string'])
        ->transform(fn ($value) => strtolower($value))
    ->cancelOn('/cancel')
    ->onCancel(fn ($ctx) => BotResponse::text('Order cancelled')->redirect('start'))
    ->onComplete([OrderSceneController::class, 'place']);

BotScene::define('survey')
    ->step('name')
        ->ask('scene_view')
        ->rules(['required', 'string'])
    ->onComplete(fn ($ctx) => BotResponse::text('Hi ' . $ctx->get('name'))->redirect('home'));

// Conditional steps, back navigation, and timeout (phase 2).
BotScene::define('signup')
    ->step('account_type')
        ->ask(fn ($ctx) => BotResponse::text('Account type?'))
        ->rules(['required', 'in:personal,business'])
    ->step('company')
        ->when(fn ($ctx) => $ctx->get('account_type') === 'business')
        ->ask(fn ($ctx) => BotResponse::text('Company name?'))
        ->rules(['required', 'string'])
    ->step('email')
        ->ask(fn ($ctx) => BotResponse::text('Email?'))
        ->rules(['required', 'email'])
    ->allowBack('/back')
    ->timeout(30)
    ->onTimeout(fn ($ctx) => BotResponse::text('Session expired')->redirect('start'))
    ->onComplete(fn ($ctx) => BotResponse::text(
        'Signup ' . $ctx->get('account_type') . '/' . ($ctx->get('company') ?? 'n/a')
    )->redirect('home'));

// Typed input via expect* extractors (phase 2).
BotScene::define('share')
    ->step('phone')
        ->expectContact()
        ->ask(fn ($ctx) => BotResponse::text('Share your contact'))
        ->rules(['required', 'array'])
    ->onComplete(fn ($ctx) => BotResponse::text('Got ' . $ctx->get('phone')['phone_number'])->redirect('home'));

BotScene::define('upload')
    ->step('pic')
        ->expectPhoto()
        ->ask(fn ($ctx) => BotResponse::text('Send a photo'))
        ->rules(['required', 'string'])
    ->onComplete(fn ($ctx) => BotResponse::text('Photo ' . $ctx->get('pic'))->redirect('home'));

// Custom error prompt on validation failure (phase 3).
BotScene::define('pin')
    ->step('code')
        ->ask(fn ($ctx) => BotResponse::text('Enter your 4-digit PIN'))
        ->rules(['required', 'digits:4'])
        ->onInvalid(fn ($ctx) => BotResponse::text('PIN must be exactly 4 digits'))
    ->onComplete(fn ($ctx) => BotResponse::text('PIN set')->redirect('home'));
