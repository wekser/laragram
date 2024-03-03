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
     * The current user station.
     *
     * @var string|null
     */
    protected ?string $station;

    /**
     * The request from to Telegram.
     *
     * @var \Illuminate\Http\Request
     */
    protected Request $request;

    /**
     * The output data.
     *
     * @var array|null
     */
    protected ?array $output;

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
        app('translator')->setLocale($this->user->settings->get('language'));

        $this->station = (config('laragram.auth.driver') != 'database') ? 'start' : $this->defineStation();
    }

    /**
     * Get or set user station.
     *
     * @return string
     */
    protected function defineStation()
    {
        return empty($session = $this->user->session()) ? 'start' : $session->station;
    }

    /**
     * Run the Laragram.
     *
     * @return void|null
     */
    protected function run()
    {
        try {
            $this->output = (new BotRouter($this->station))->dispatch($this->request->all());
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
        if (!empty($this->output) && config('laragram.auth.driver') == 'database') {
            event(new CallbackFormed($this->user, $this->output));
        }
    }

    /**
     * The Laragram back response.
     *
     * @return mixed
     */
    protected function back()
    {
        return empty($this->output) ? response('OK', 200) : response()->json($this->output['response']['view']);
    }
}
