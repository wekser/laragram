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

use Exception;
use Illuminate\Http\Request;
use Wekser\Laragram\Events\CallbackFormed;
use Wekser\Laragram\Exceptions\BotException;
use Wekser\Laragram\Facades\BotAuth;

class Laragram
{
    /**
     * The current user location.
     *
     * @var string|null
     */
    protected $location;

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
     * @var User
     */
    protected $user;

    /**
     * Laragram Constructor
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
        app('translator')->setLocale($this->user->settings['language']);

        if (config('laragram.auth.driver') == 'database') $this->location = $this->user->sessions()->latest()->value('location');
    }

    /**
     * Run the Laragram.
     *
     * @return void|null
     */
    protected function run()
    {
        try {
            $this->response = (new BotRouter())->dispatch($this->request->all(), $this->location);
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
        if (!empty($this->response) && config('laragram.auth.driver') == 'database') {
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
        return empty($this->response) ? response('ok', 200) : response()->json($this->response['view']);
    }
}
