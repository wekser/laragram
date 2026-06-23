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
use Wekser\Laragram\Http\ResponseDispatcher;
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

        $this->deliver();

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
     * Deliver the formed responses to Telegram as outbound Bot API calls.
     *
     * Runs after the session is persisted (fireEvent), so the user's next station
     * is recorded even if a message fails to deliver. Sends nothing when no route
     * matched or the controller returned no response.
     *
     * @return void
     */
    protected function deliver(): void
    {
        if (empty($this->output)) {
            return;
        }

        app(ResponseDispatcher::class)->send($this->output['response']['views'] ?? []);
    }

    /**
     * The Laragram back response.
     *
     * Messages are delivered via outbound Bot API calls in deliver(), so the
     * webhook body itself is always an empty 'OK' 200 — Telegram only needs to
     * know the update was received.
     *
     * @return mixed
     */
    protected function back(): mixed
    {
        return response('OK', 200);
    }
}
