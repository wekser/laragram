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
     * @var User
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
     * The basic or custom User model.
     *
     * @var object
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
     * BotAuth Constructor
     *
     * @param \Illuminate\Http\Request $request
     * @param string $driver
     * @param object $model
     * @param array $languages
     */
    public function __construct(Request $request, string $driver, object $model, array $languages)
    {
        $this->request = $request;
        $this->driver = $driver;
        $this->model = $model;
        $this->languages = $languages;
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
            $current = empty($user = $this->getUser($sender)) ? $this->register($sender) : $this->login($user, $sender);
        } else {
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
    protected function defineSender(): array
    {
        $entity = collect($this->request->all())->first(function ($value, $key) {
            return is_array($value) && isset($value['from']);
        });

        return $this->sender = collect($entity)->get('from');
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
     * @return object|null
     */
    protected function getUser(array $sender)
    {
        return (new ($this->model))::where('uid', $sender['id'])->first();
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
    protected function defineUserLanguage(array $sender): string
    {
        $userLanguage = $sender['language_code'] ?? null;

        return !empty($userLanguage) && in_array($userLanguage, $this->getBotLanguages()) ? $userLanguage : app('translator')->getLocale();
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
     * @param object $user
     * @param array $sender
     * @return object
     */
    protected function login(object $user, array $sender)
    {
        $user->first_name = $sender['first_name'];
        $user->last_name = $sender['last_name'] ?? null;
        $user->username = $sender['username'] ?? null;
        $user->save();

        return $user;
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
            'language' => $this->defineUserLanguage($sender)
        ];

        return (object) $user;
    }

    /**
     * Get current authorized user.
     *
     * @return object
     */
    public function user()
    {
        return $this->current;
    }
}