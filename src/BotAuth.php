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
     * @var User
     */
    protected $current;

    /**
     * The array languages in bot.
     *
     * @var array
     */
    protected $languages;

    /**
     * Sender of the request.
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
        if (empty($this->defineSender())) {
            return $this;
        }

        $user = $this->getUser();

        $this->current = empty($user) ? $this->register($this->sender) : $this->login($user, $this->sender);

        return $this;
    }

    /**
     * Define sender from request.
     *
     * @return array|null
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
        $user->language = in_array(array_get($sender, 'language_code'), $this->getBotLanguages()) ? array_get($sender, 'language_code') : app('translator')->getLocale();
        $user->save();

        return $user;
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
     * @return \Wekser\Laragram\Models\User|null
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