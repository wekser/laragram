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

class BotAuth
{
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
     * The current request.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * BotAuth Constructor
     *
     * @param \Illuminate\Http\Request $request
     * @param string $driver
     * @param array $languages
     */
    public function __construct(Request $request, $driver, $languages)
    {
        $this->request = $request;
        $this->driver = $driver;
        $this->languages = $languages;
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
            $current = empty($user = $this->getUser($sender)) ? $this->register($sender) : $this->login($user, $sender);
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
        $entity = collect($request->all())->first(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        });
        
        return $this->sender = collect($entity)->get('from');
    }

    /**
     * Get user from database.
     *
     * @param array $sender
     * @return \Wekser\Laragram\Models\User|null
     */
    protected function getUser(array $sender)
    {
        return User::where('uid', $sender['id'])->first();
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
            'uid' => $sender['id'],
            'first_name' => $sender['first_name'],
            'last_name' => $sender['last_name'] ?? null,
            'username' => $sender['username'] ?? null,
            'language' => $this->defineUserLanguage($sender)
        ];

        return (object)$user;
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
        $user->uid = $sender['id'];
        $user->first_name = $sender['first_name'];
        $user->last_name = $sender['last_name'] ?? null;
        $user->username = $sender['username'] ?? null;
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
        $userLanguage = $sender['language_code'] ?? null;

        $appLanguage = app('translator')->getLocale();

        return !empty($userLanguage) && in_array($userLanguage, $this->getBotLanguages()) ? $userLanguage : $appLanguage;
    }

    /**
     * Get array languages in bot.
     *
     * @return array|null
     */
    public function getBotLanguages()
    {
        return $this->languages;
    }

    /**
     * Get authentication driver.
     *
     * @return string
     */
    public function getDriver()
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
        $user->first_name = $sender['first_name'];
        $user->last_name = $sender['last_name'] ?? null;
        $user->username = $sender['username'] ?? null;
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
}