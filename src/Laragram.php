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

use Exception;
use Illuminate\Http\Request;
use Wekser\Laragram\Events\CallbackFormed;
use Wekser\Laragram\Exceptions\BotException;
use Wekser\Laragram\Facades\BotAuth;
use Wekser\Laragram\Support\Aidable;

class Laragram
{
    use Aidable;

    /**
     * The current user state.
     *
     * @var string|null
     */
    protected $state;

    /**
     * The request from to Telegram.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The response back to webhook.
     *
     * @var array|null
     */
    protected $response;

    /**
     * The current authorized user.
     *
     * @var \Wekser\Laragram\Models\User
     */
    protected $user;

    /**
     * Laragram Constructor
     *
     */
    public function __construct()
    {
        $this->user = BotAuth::user();
    }

    /**
     * The entry point into the Laragram.
     *
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $this->request = $request;

        $this->bootstrap();

        $this->run();

        $this->fireEvent();

        return $this->back();
    }

    /**
     * Create the Laragram.
     *
     * @return void
     */
    protected function bootstrap()
    {
        $language = $this->user->language;

        app('translator')->setLocale($language);

        if ($this->config('auth.driver') == 'database') {
            $this->state = $this->user->sessions()->latest()->value('last_state');
        }
    }

    /**
     * Run the Laragram.
     *
     * @return void
     */
    protected function run()
    {
        try {
            $this->response = (new BotRoute())->dispatch($this->request->all(), $this->state);
        } catch (Exception $exception) {
            return BotException::handle($exception);
        }
    }

    /**
     * To trigger an event on the Laragram response.
     *
     * @return void
     */
    protected function fireEvent()
    {
        if (! empty($this->response) && $this->config('auth.driver') == 'database') {
            event(new CallbackFormed($this->user, $this->response));
        }
    }

    /**
     * The Laragram back response.
     *
     * @return mixed
     */
    protected function back()
    {
        return response()->json(array_get($this->response, 'view'));
    }
}
