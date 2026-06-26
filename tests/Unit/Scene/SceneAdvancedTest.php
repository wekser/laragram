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
use Wekser\Laragram\Routing\Router;
use Wekser\Laragram\Scene\Scene;
use Wekser\Laragram\Scene\SceneManager;
use Wekser\Laragram\Scene\SceneRegistry;
use Wekser\Laragram\Scene\Step;
use Wekser\Laragram\Testing\BotUpdateFactory;
use Wekser\Laragram\Tests\TestCase;
use Wekser\Laragram\View\ComponentContext;

/**
 * Phase 2 behaviours: conditional steps (when), back navigation, scene timeout,
 * global escape commands, and the expect* typed extractors.
 */
#[CoversClass(SceneManager::class)]
#[CoversClass(Scene::class)]
#[CoversClass(Step::class)]
class SceneAdvancedTest extends TestCase
{
    private SceneManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        config(['laragram.paths.scenes' => 'laragram/scenes']);
        SceneRegistry::flushCache();
        Router::flushCache();
        BotUpdateFactory::reset();

        $this->manager = app(SceneManager::class);
    }

    protected function tearDown(): void
    {
        SceneRegistry::flushCache();
        Router::flushCache();
        BotUpdateFactory::reset();
        ComponentContext::reset();

        parent::tearDown();
    }

    private function state(string $name, string $step, array $data = [], ?int $at = null): array
    {
        return ['name' => $name, 'step' => $step, 'data' => $data, 'at' => $at ?? time()];
    }

    // ----- Conditional steps (when) ------------------------------------------

    public function test_ineligible_step_is_skipped_when_condition_fails(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('personal'),
            '@scene:signup',
            $this->state('signup', 'account_type'),
        );

        // 'company' (business-only) is skipped → straight to 'email'.
        $this->assertSame('email', $output['scene']['step']);
        $this->assertSame(['account_type' => 'personal'], $output['scene']['data']);
    }

    public function test_eligible_step_is_kept_when_condition_passes(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('business'),
            '@scene:signup',
            $this->state('signup', 'account_type'),
        );

        $this->assertSame('company', $output['scene']['step']);
    }

    // ----- Back navigation ---------------------------------------------------

    public function test_back_returns_to_previous_eligible_step(): void
    {
        // On 'email' as a personal account: 'company' was skipped, so back lands
        // on 'account_type', not the skipped 'company' step.
        $output = $this->manager->continue(
            BotUpdateFactory::message('/back'),
            '@scene:signup',
            $this->state('signup', 'email', ['account_type' => 'personal']),
        );

        $this->assertSame('@scene:signup', $output['response']['redirect']);
        $this->assertSame('account_type', $output['scene']['step']);
        $this->assertStringContainsString('Account type?', $output['response']['views'][0]['text']);
    }

    public function test_back_on_first_step_reasks_first_step(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('/back'),
            '@scene:signup',
            $this->state('signup', 'account_type'),
        );

        $this->assertSame('account_type', $output['scene']['step']);
    }

    public function test_changing_earlier_answer_prunes_now_ineligible_answers(): void
    {
        // Was a business account with a 'company' answer; going back and
        // switching to 'personal' makes the conditional 'company' step
        // ineligible, so its stale answer must be dropped before onComplete.
        $output = $this->manager->continue(
            BotUpdateFactory::message('personal'),
            '@scene:signup',
            $this->state('signup', 'account_type', ['account_type' => 'business', 'company' => 'Acme']),
        );

        $this->assertSame('email', $output['scene']['step']);
        $this->assertSame(['account_type' => 'personal'], $output['scene']['data']);
        $this->assertArrayNotHasKey('company', $output['scene']['data']);
    }

    // ----- Timeout -----------------------------------------------------------

    public function test_scene_expires_after_timeout(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('personal'),
            '@scene:signup',
            $this->state('signup', 'account_type', [], at: time() - 9999),
        );

        $this->assertSame('start', $output['response']['redirect']);
        $this->assertNull($output['scene']);
        $this->assertStringContainsString('Session expired', $output['response']['views'][0]['text']);
    }

    public function test_scene_within_timeout_is_not_expired(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('personal'),
            '@scene:signup',
            $this->state('signup', 'account_type', [], at: time() - 60),
        );

        $this->assertNotNull($output['scene']);
        $this->assertSame('email', $output['scene']['step']);
    }

    public function test_invalid_input_does_not_refresh_the_timeout(): void
    {
        // Re-asking on validation failure is not progress, so it must preserve
        // the original 'at' — otherwise a stream of invalid answers would keep
        // the scene alive past its inactivity timeout indefinitely.
        $at = time() - 600;

        $output = $this->manager->continue(
            BotUpdateFactory::message('not-a-valid-type'),
            '@scene:signup',
            $this->state('signup', 'account_type', [], at: $at),
        );

        $this->assertSame('account_type', $output['scene']['step']);
        $this->assertSame($at, $output['scene']['at']);
    }

    // ----- Global escape commands --------------------------------------------

    public function test_global_command_escapes_scene_and_routes_normally(): void
    {
        config([
            'laragram.paths.route'           => 'multi',
            'laragram.scenes.global_commands' => ['/multi'],
        ]);
        Router::flushCache();

        $output = $this->manager->continue(
            BotUpdateFactory::message('/multi'),
            '@scene:order',
            $this->state('order', 'size'),
        );

        // Handled by the normal router (the /multi route), scene cleared.
        $this->assertNull($output['scene']);
        $this->assertSame('done', $output['response']['redirect']);
        $this->assertCount(2, $output['response']['views']);
    }

    public function test_global_command_can_start_a_new_scene(): void
    {
        // The escaped-to handler returns BotScene::enter('order'); escape() must
        // keep that freshly-started scene's state instead of clearing it.
        config([
            'laragram.paths.route'            => 'scene_enter',
            'laragram.scenes.global_commands' => ['/enter'],
        ]);
        Router::flushCache();

        $output = $this->manager->continue(
            BotUpdateFactory::message('/enter'),
            '@scene:survey',
            $this->state('survey', 'name'),
        );

        $this->assertNotNull($output['scene']);
        $this->assertSame('order', $output['scene']['name']);
        $this->assertSame('@scene:order', $output['response']['redirect']);
    }

    public function test_global_command_with_no_matching_route_resets(): void
    {
        config(['laragram.scenes.global_commands' => ['/nope']]);

        $output = $this->manager->continue(
            BotUpdateFactory::message('/nope'),
            '@scene:order',
            $this->state('order', 'size'),
        );

        $this->assertNull($output['scene']);
        $this->assertSame('start', $output['response']['redirect']);
        $this->assertSame([], $output['response']['views']);
    }

    // ----- expect* extractors ------------------------------------------------

    public function test_expect_contact_reads_the_contact_object(): void
    {
        $update = [
            'update_id' => 1,
            'message'   => [
                'message_id' => 1,
                'from'       => ['id' => 100, 'is_bot' => false, 'first_name' => 'T'],
                'chat'       => ['id' => 100, 'type' => 'private'],
                'date'       => time(),
                'contact'    => ['phone_number' => '+1555', 'first_name' => 'T'],
            ],
        ];

        $output = $this->manager->continue($update, '@scene:share', $this->state('share', 'phone'));

        $this->assertNull($output['scene']);
        $this->assertSame('home', $output['response']['redirect']);
        $this->assertStringContainsString('Got +1555', $output['response']['views'][0]['text']);
    }

    // ----- onInvalid custom error prompt -------------------------------------

    public function test_on_invalid_prompt_is_shown_on_validation_failure(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('12'),
            '@scene:pin',
            $this->state('pin', 'code'),
        );

        $this->assertSame('code', $output['scene']['step']);
        $this->assertStringContainsString('PIN must be exactly 4 digits', $output['response']['views'][0]['text']);
    }

    public function test_valid_input_completes_pin_scene(): void
    {
        $output = $this->manager->continue(
            BotUpdateFactory::message('1234'),
            '@scene:pin',
            $this->state('pin', 'code'),
        );

        $this->assertNull($output['scene']);
        $this->assertSame('home', $output['response']['redirect']);
        $this->assertStringContainsString('PIN set', $output['response']['views'][0]['text']);
    }

    public function test_expect_photo_reads_the_largest_file_id(): void
    {
        $update = [
            'update_id' => 1,
            'message'   => [
                'message_id' => 1,
                'from'       => ['id' => 100, 'is_bot' => false, 'first_name' => 'T'],
                'chat'       => ['id' => 100, 'type' => 'private'],
                'date'       => time(),
                'photo'      => [
                    ['file_id' => 'small', 'width' => 90],
                    ['file_id' => 'large', 'width' => 1280],
                ],
            ],
        ];

        $output = $this->manager->continue($update, '@scene:upload', $this->state('upload', 'pic'));

        $this->assertStringContainsString('Photo large', $output['response']['views'][0]['text']);
    }
}
