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

namespace Wekser\Laragram;

use Illuminate\Http\Request;
use Wekser\Laragram\Events\CallbackFormed;
use Wekser\Laragram\Exceptions\ExceptionHandler;
use Wekser\Laragram\Facades\BotAuth;
use Wekser\Laragram\Models\User;
use Wekser\Laragram\Routing\Router;

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
     * @var User|null
     */
    protected ?User $user;

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
    public function index(Request $request): mixed
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
    protected function bootstrap(): void
    {
        $locale = $this->user?->settings?->get('language', config('app.locale'));
        app('translator')->setLocale($locale ?? config('app.locale') ?? 'en');

        $this->station = (config('laragram.auth.driver') !== 'database') ? 'start' : $this->defineStation();
    }

    /**
     * Get or set user station.
     *
     * @return string
     */
    protected function defineStation(): string
    {
        return empty($session = $this->user?->session()) ? 'start' : $session->station;
    }

    /**
     * Run the Laragram.
     *
     * @return void|null
     */
    protected function run()
    {
        try {
            $this->output = (new Router($this->station))->dispatch($this->request->all());
        } catch (\Throwable $exception) {
            ExceptionHandler::handle($exception);
        }
    }

    /**
     * To trigger an event on the Laragram response.
     *
     * @return void
     */
    protected function fireEvent(): void
    {
        if (!empty($this->output)) {
            event(new CallbackFormed($this->user, $this->output));
        }
    }

    /**
     * The Laragram back response.
     *
     * @return mixed
     */
    protected function back(): mixed
    {
        if (empty($this->output)) {
            return response('OK', 200);
        }

        $view = $this->output['response']['view'] ?? [];

        return response()->json($view);
    }
}
