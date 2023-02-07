<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram;

use Illuminate\Http\Request;

class BotAuth
{
    /**
     * Current authorized user.
     *
     * @var mixed
     */
    protected $user;

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
     * The basic or custom User model.
     *
     * @var mixed
     */
    protected $model;

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
     * BotAuth Constructor.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $driver
     * @param array $languages
     * @param mixed $model
     */
    public function __construct(Request $request, string $driver, array $languages, $model)
    {
        $this->request = $request;
        $this->driver = $driver;
        $this->languages = $languages;
        $this->model = $model;
    }

    /**
     * Run the authorization service.
     *
     * @return $this
     */
    public function authenticate(): self
    {
        $sender = $this->defineSender();

        $driver = $this->getDriver();

        if ($driver == 'database') {
            if ($user = $this->getUser($sender)) {
                $this->user = $this->login($user, $sender);
            } else {
                $this->user = $this->register($sender);
            }
        } else {
            $this->user = $this->setUser($sender);
        }

        return $this;
    }

    /**
     * Define sender from request.
     *
     * @return array
     */
    protected function defineSender(): array
    {
        return $this->sender = collect(collect($this->request->all())->first(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        }))->get('from');
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
     * Get user from database.
     *
     * @param array $sender
     * @return mixed
     */
    protected function getUser(array $sender)
    {
        return (new ($this->model))::where('uid', $sender['id'])->first();
    }

    /**
     * Set user for array driver.
     *
     * @param array $sender
     * @return object
     */
    protected function setUser(array $sender): object
    {
        $user = [
            'uid' => $sender['id'],
            'first_name' => $sender['first_name'],
            'last_name' => $sender['last_name'] ?? null,
            'username' => $sender['username'] ?? null,
            'settings' => ['active' => true, 'language' => $this->defineUserLanguage($sender)]
        ];

        return (object) $user;
    }

    /**
     * Create a new user.
     *
     * @param array $sender
     * @return object
     */
    protected function register(array $sender)
    {
        $user = new ($this->model)();
        $user->uid = $sender['id'];
        return $this->extracted($sender, $user);
    }

    /**
     * Define user language.
     *
     * @param array $sender
     * @return string
     */
    protected function defineUserLanguage(array $sender): string
    {
        $userLanguage = $sender['language_code'] ?? null;

        return !empty($userLanguage) && in_array($userLanguage, $this->languages) ? $userLanguage : app('translator')->getLocale();
    }

    /**
     * Update user when login.
     *
     * @param mixed $user
     * @param array $sender
     * @return object
     */
    protected function login(mixed $user, array $sender)
    {
        return $this->extracted($sender, $user);
    }

    /**
     * Get authorized user.
     *
     * @return object
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * @param array $sender
     * @param mixed $user
     * @return mixed
     */
    protected function extracted(array $sender, mixed $user): mixed
    {
        $user->first_name = $sender['first_name'];
        $user->last_name = $sender['last_name'] ?? null;
        $user->username = $sender['username'] ?? null;
        $user->settings = ['active' => true, 'language' => $this->defineUserLanguage($sender)];
        $user->save();

        return $user;
    }
}