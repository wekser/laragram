<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram;

use Illuminate\Http\Request;
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Support\Authenticatable;

class BotAuth
{
    use Authenticatable;

    /**
     * Current authorized user.
     *
     * @var User|object
     */
    protected $current;

    /**
     * The authentication driver.
     *
     * @var string
     */
    protected $driver;

    /**
     * The array languages in bot.
     *
     * @var array
     */
    protected $languages;

    /**
     * User object from the request.
     *
     * @var array
     */
    protected $sender;

    /**
     * Secure payload.
     *
     * @var bool
     */
    protected $securePayload;

    /**
     * The current request.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * BotAuth Constructor
     *
     * @param \Illuminate\Http\Request $request
     * @param array $configuration
     */
    public function __construct(Request $request, array $configuration)
    {
        $this->request = $request;
        $this->driver = array_get($configuration, 'driver');
        $this->languages = array_get($configuration, 'languages');
        $this->securePayload = array_get($configuration, 'secure_payload', true);
    }

    /**
     * Run the authorization service.
     *
     * @return $this
     */
    public function authenticate()
    {
        $sender = $this->defineSender();

        $driver = $this->getDriver();

        if ($driver == 'database') {
            $current = empty($user = $this->getUser()) ? $this->register($sender) : $this->login($user, $sender);
        } elseif ($driver == 'array') {
            $current = $this->setUser($sender);
        }

        $this->current = $current;

        return $this;
    }

    /**
     * Define sender from request.
     *
     * @return array
     */
    protected function defineSender()
    {
        $listener = array_get(array_keys($this->request->all()), 1);

        $event = array_get($this->request->all(), $listener);
        
        return $this->sender = array_get($event, 'from');
    }

    /**
     * Get user from database.
     *
     * @return \Wekser\Laragram\Models\User|null
     */
    protected function getUser()
    {
        return User::where('uid', array_get($this->sender, 'id'))->first();
    }

    /**
     * Set user for array driver.
     *
     * @param array $sender
     * @return object
     */
    protected function setUser(array $sender)
    {
        $user = [
            'uid' => array_get($sender, 'id'),
            'first_name' => array_get($sender, 'first_name'),
            'last_name' => array_get($sender, 'last_name'),
            'username' => array_get($sender, 'username'),
            'language' => $this->defineUserLanguage($sender)
        ];

        return (object) $user;
    }

    /**
     * Create a new user.
     *
     * @param array $sender
     * @return \Wekser\Laragram\Models\User
     */
    protected function register(array $sender)
    {
        $user = new User();
        $user->uid = array_get($sender, 'id');
        $user->first_name = array_get($sender, 'first_name');
        $user->last_name = array_get($sender, 'last_name');
        $user->username = array_get($sender, 'username');
        $user->language = $this->defineUserLanguage($sender);
        $user->save();

        return $user;
    }

    /**
     * Define a user language.
     *
     * @param array $sender
     * @return string
     */
    protected function defineUserLanguage(array $sender)
    {
        $userLanguage = array_get($sender, 'language_code');
        $appLanguage = app('translator')->getLocale();

        return in_array($userLanguage, $this->getBotLanguages()) ? $userLanguage : $appLanguage;
    }

    /**
     * Get array languages in bot.
     *
     * @return array|null
     */
    public function getBotLanguages(): ?array
    {
        return $this->languages;
    }

    /**
     * Get authentication driver.
     *
     * @return string
     */
    public function getDriver(): string
    {
        return $this->driver ?? 'array';
    }

    /**
     * Update user when login.
     *
     * @param \Wekser\Laragram\Models\User $user
     * @param array $sender
     * @return \Wekser\Laragram\Models\User
     */
    protected function login(User $user, array $sender)
    {
        $user->first_name = array_get($sender, 'first_name');
        $user->last_name = array_get($sender, 'last_name');
        $user->username = array_get($sender, 'username');
        $user->save();

        return $user;
    }

    /**
     * Get current authorized user.
     *
     * @return \Wekser\Laragram\Models\User|object
     */
    public function user()
    {
        return $this->current;
    }

    /**
     * Has secured payload.
     *
     * @return bool
     */
    public function isSecurePayload(): bool
    {
        return $this->securePayload;
    }
}