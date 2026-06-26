<?php
declare(strict_types=1);

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Tests\Unit\Scene;

use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\Http\RequestTransformer;
use Wekser\Laragram\Scene\SceneContext;
use Wekser\Laragram\Scene\SceneManager;
use Wekser\Laragram\Scene\SceneRegistry;
use Wekser\Laragram\Scene\SceneTransition;
use Wekser\Laragram\Scene\Step;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;

#[CoversClass(SceneManager::class)]
#[CoversClass(SceneContext::class)]
#[CoversClass(SceneTransition::class)]
#[CoversClass(Step::class)]
class SceneManagerTest extends TestCase
{
    private SceneManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config(['laragram.paths.scenes' => 'laragram/scenes']);
        SceneRegistry::flushCache();
        BotUpdateFactory::reset();

        $this->manager = app(SceneManager::class);
    }

    protected function tearDown(): void
    {
        SceneRegistry::flushCache();
        BotUpdateFactory::reset();
        ComponentContext::reset();

        parent::tearDown();
    }

    private function state(string $name, string $step, array $data = []): array
    {
        return ['name' => $name, 'step' => $step, 'data' => $data];
    }

    public function test_valid_answer_advances_to_the_next_step(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('M'),
            '@scene:order',
            $this->state('order', 'size'),
        );

        $this->assertSame('@scene:order', $output['response']['redirect']);
        $this->assertSame('color', $output['scene']['step']);
        $this->assertSame(['size' => 'M'], $output['scene']['data']);
        $this->assertSame('sendMessage', $output['response']['views'][0]['method']);
        $this->assertStringContainsString('color for size M', $output['response']['views'][0]['text']);
    }

    public function test_invalid_answer_repeats_the_same_step(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('XL'),
            '@scene:order',
            $this->state('order', 'size'),
        );

        $this->assertSame('@scene:order', $output['response']['redirect']);
        $this->assertSame('size', $output['scene']['step']);
        $this->assertSame([], $output['scene']['data']);
        $this->assertStringContainsString('Choose a size', $output['response']['views'][0]['text']);
    }

    public function test_transform_is_applied_and_last_step_completes(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('RED'),
            '@scene:order',
            $this->state('order', 'color', ['size' => 'M']),
        );

        // onComplete redirected to 'home' and cleared the scene.
        $this->assertSame('home', $output['response']['redirect']);
        $this->assertNull($output['scene']);
        // strtolower transform applied before the answer reached the handler.
        $this->assertStringContainsString('Done M/red', $output['response']['views'][0]['text']);
    }

    public function test_full_flow_threaded_across_turns(): void
    {
        $first = $this->manager->continue(
            BotUpdateFactory::message('S'),
            '@scene:order',
            $this->state('order', 'size'),
        );

        $this->assertSame('color', $first['scene']['step']);

        // Feed the persisted state back in, as the session would across requests.
        $second = $this->manager->continue(
            BotUpdateFactory::message('Blue'),
            '@scene:order',
            $first['scene'],
        );

        $this->assertSame('home', $second['response']['redirect']);
        $this->assertNull($second['scene']);
        $this->assertStringContainsString('Done S/blue', $second['response']['views'][0]['text']);
    }

    public function test_cancel_command_aborts_the_scene(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('/cancel'),
            '@scene:order',
            $this->state('order', 'color', ['size' => 'M']),
        );

        $this->assertSame('start', $output['response']['redirect']);
        $this->assertNull($output['scene']);
        $this->assertStringContainsString('Order cancelled', $output['response']['views'][0]['text']);
    }

    public function test_unknown_scene_resets_to_start_without_sending(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('hi'),
            '@scene:ghost',
            null,
        );

        $this->assertSame('start', $output['response']['redirect']);
        $this->assertNull($output['scene']);
        $this->assertSame([], $output['response']['views']);
    }

    public function test_corrupt_step_resets_to_start(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('hi'),
            '@scene:order',
            $this->state('order', 'no_such_step'),
        );

        $this->assertSame('start', $output['response']['redirect']);
        $this->assertNull($output['scene']);
    }

    public function test_start_renders_first_step_from_a_view_prompt(): void
    {
        $update  = BotUpdateFactory::message('/survey');
        $request = (new RequestTransformer('message', $update))
            ->build(['event' => 'message', 'listener' => 'text', 'contains' => null], 'start');

        $output = $this->manager->start($request, new SceneTransition('survey'));

        $this->assertSame('@scene:survey', $output['response']['redirect']);
        $this->assertSame('survey', $output['scene']['name']);
        $this->assertSame('name', $output['scene']['step']);
        $this->assertStringContainsString('What is your name?', $output['response']['views'][0]['text']);
    }

    public function test_enter_requires_the_database_driver(): void
    {
        config(['laragram.auth.driver' => 'array']);

        $this->expectException(\RuntimeException::class);

        $this->manager->enter('order');
    }

    public function test_enter_returns_a_transition_under_database_driver(): void
    {
        config(['laragram.auth.driver' => 'database']);

        $transition = $this->manager->enter('order', ['promo' => 'X']);

        $this->assertSame('order', $transition->name);
        $this->assertSame(['promo' => 'X'], $transition->initialData);
    }
}
