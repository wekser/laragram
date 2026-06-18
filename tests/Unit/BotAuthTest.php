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

namespace Wekser\Laragram\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Wekser\Laragram\BotAuth;
use Wekser\Laragram\Exceptions\AuthenticationException;
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Tests\TestCase;

#[CoversClass(BotAuth::class)]
class BotAuthTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAuth(array $requestData = []): BotAuth
    {
        return new BotAuth(
            Request::create('/', 'POST', $requestData),
            'array',
            ['en', 'ru'],
            User::class,
        );
    }

    private function validPayload(array $senderOverrides = []): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'from' => array_merge([
                    'id'            => 123,
                    'first_name'    => 'Ivan',
                    'last_name'     => 'Petrov',
                    'username'      => 'ivan_p',
                    'language_code' => 'ru',
                ], $senderOverrides),
                'text' => 'hello',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // authenticate()
    // -------------------------------------------------------------------------

    public function test_authenticate_throws_when_request_is_empty(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->makeAuth()->authenticate();
    }

    public function test_authenticate_returns_self_for_chaining(): void
    {
        $auth = $this->makeAuth($this->validPayload());

        $this->assertSame($auth, $auth->authenticate());
    }

    public function test_authenticate_creates_user_instance(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();

        $this->assertInstanceOf(User::class, $auth->user());
    }

    public function test_authenticate_maps_sender_fields_to_user(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();
        $user = $auth->user();

        $this->assertSame(123, $user->uid);
        $this->assertSame('Ivan', $user->first_name);
        $this->assertSame('Petrov', $user->last_name);
        $this->assertSame('ivan_p', $user->username);
    }

    // -------------------------------------------------------------------------
    // user() / isAuthenticated()
    // -------------------------------------------------------------------------

    public function test_user_is_null_before_authenticate(): void
    {
        $this->assertNull($this->makeAuth($this->validPayload())->user());
    }

    public function test_is_authenticated_false_before_authenticate(): void
    {
        $this->assertFalse($this->makeAuth($this->validPayload())->isAuthenticated());
    }

    public function test_is_authenticated_true_after_authenticate(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();

        $this->assertTrue($auth->isAuthenticated());
    }

    // -------------------------------------------------------------------------
    // getDriver()
    // -------------------------------------------------------------------------

    public function test_get_driver_returns_configured_driver(): void
    {
        $this->assertSame('array', $this->makeAuth()->getDriver());
    }

    // -------------------------------------------------------------------------
    // getSender() / getUserId()
    // -------------------------------------------------------------------------

    public function test_get_sender_is_null_before_authenticate(): void
    {
        $this->assertNull($this->makeAuth($this->validPayload())->getSender());
    }

    public function test_get_sender_returns_structured_data_after_authenticate(): void
    {
        $auth   = $this->makeAuth($this->validPayload())->authenticate();
        $sender = $auth->getSender();

        $this->assertIsArray($sender);
        $this->assertSame(123, $sender['id']);
        $this->assertSame('Ivan', $sender['first_name']);
    }

    public function test_get_user_id_returns_null_before_authenticate(): void
    {
        $this->assertNull($this->makeAuth($this->validPayload())->getUserId());
    }

    public function test_get_user_id_returns_telegram_user_id(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();

        $this->assertSame(123, $auth->getUserId());
    }

    // -------------------------------------------------------------------------
    // getUserLanguage()
    // -------------------------------------------------------------------------

    public function test_get_user_language_returns_app_locale_before_authenticate(): void
    {
        $this->assertSame(app()->getLocale(), $this->makeAuth()->getUserLanguage());
    }

    public function test_get_user_language_returns_valid_language_from_sender(): void
    {
        $auth = $this->makeAuth($this->validPayload(['language_code' => 'ru']))->authenticate();

        $this->assertSame('ru', $auth->getUserLanguage());
    }

    public function test_get_user_language_falls_back_to_locale_for_unsupported_code(): void
    {
        $auth = $this->makeAuth($this->validPayload(['language_code' => 'zz']))->authenticate();

        $this->assertSame(app()->getLocale(), $auth->getUserLanguage());
    }

    // -------------------------------------------------------------------------
    // getUserFullName()
    // -------------------------------------------------------------------------

    public function test_get_user_full_name_returns_empty_string_before_authenticate(): void
    {
        $this->assertSame('', $this->makeAuth()->getUserFullName());
    }

    public function test_get_user_full_name_combines_first_and_last_name(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();

        $this->assertSame('Ivan Petrov', $auth->getUserFullName());
    }

    public function test_get_user_full_name_trims_when_no_last_name(): void
    {
        $payload = $this->validPayload();
        unset($payload['message']['from']['last_name']);

        $auth = $this->makeAuth($payload)->authenticate();

        $this->assertSame('Ivan', $auth->getUserFullName());
    }

    // -------------------------------------------------------------------------
    // isUserActive() — uses User::isActive() (is_active DB column)
    // -------------------------------------------------------------------------

    public function test_is_user_active_returns_false_when_not_authenticated(): void
    {
        $this->assertFalse($this->makeAuth()->isUserActive());
    }

    public function test_is_user_active_returns_true_for_fresh_array_driver_user(): void
    {
        // Array driver creates a new User without is_active set.
        // User::isActive() returns ($this->is_active ?? true) → true.
        $auth = $this->makeAuth($this->validPayload())->authenticate();

        $this->assertTrue($auth->isUserActive());
    }

    public function test_is_user_active_returns_false_when_user_is_deactivated(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();
        $user = $auth->user();
        $user->is_active = false;

        $this->assertFalse($auth->isUserActive());
    }

    // -------------------------------------------------------------------------
    // Settings — active key removed, language key present
    // -------------------------------------------------------------------------

    public function test_user_settings_do_not_contain_active_key(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();

        $this->assertFalse($auth->user()->settings->has('active'));
    }

    public function test_user_settings_contain_language_key(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();

        $this->assertTrue($auth->user()->settings->has('language'));
    }

    // -------------------------------------------------------------------------
    // logout()
    // -------------------------------------------------------------------------

    public function test_logout_clears_user_and_sender(): void
    {
        $auth = $this->makeAuth($this->validPayload())->authenticate();
        $auth->logout();

        $this->assertNull($auth->user());
        $this->assertNull($auth->getSender());
    }
}
