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
use Wekser\Laragram\Exceptions\NotExistsViewException;
use Wekser\Laragram\Exceptions\ViewEmptyException;
use Wekser\Laragram\Exceptions\ViewInvalidException;
use Wekser\Laragram\Facades\BotAuth;

class BotResponse
{
    /**
     * The state to next user request.
     *
     * @var string
     */
    public $state;

    /**
     * The contents of rendered view.
     *
     * @var array
     */
    public $contents = [];

    /**
     * The array of view data.
     *
     * @var array
     */
    protected $data;

    /**
     * The path to the view file.
     *
     * @var string
     */
    protected $path;

    /**
     * The current view name.
     *
     * @var string
     */
    protected $view;

    /**
     * The path where all views are placed.
     *
     * @var string
     */
    protected $viewsPath;

    /**
     * The current view user.
     *
     * @var BotAuth|User
     */
    protected $user;

    /**
     * Callback Constructor
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        $this->viewsPath = $path;
        $this->user = BotAuth::user();
    }

    /**
     * Set the user of the view.
     *
     * @param User $view
     * @return $this
     */
    public function user($user): self
    {
        $model = config('laragram.auth.model');

        $this->user = !($user instanceof $model) ?: $user;

        return $this;
    }

    /**
     * Set the state to next user request.
     *
     * @param string $state
     * @return $this
     */
    public function redirect(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Create the basic view response.
     *
     * @param string $text
     * @return $this
     */
    public function text(string $text): self
    {
        $view['method'] = 'sendMessage';
        $view['chat_id'] = $this->user->uid;
        $view['text'] = $text;
        $view['parse_mode'] = 'Markdown';

        $this->contents = $view;

        return $this;
    }

    /**
     * Get the contents of the view.
     *
     * @param string $view
     * @param array|null $data
     * @return $this
     */
    public function view(string $view, ?array $data = []): self
    {
        $this->contents = $this->render($view, $data);

        return $this;
    }

    /**
     * Get the contents of the view.
     *
     * @param string $view
     * @param array|null $data
     * @return array
     */
    protected function render(string $view, ?array $data): array
    {
        $this->view = $view;

        $this->setPath($view);

        $this->setData($data);

        return $this->renderContents();
    }

    /**
     * Set the path to the view.
     *
     * @param string $view
     * @return void
     */
    protected function setPath(string $view)
    {
        $this->path = resource_path($this->viewsPath . '/' . str_replace('.', '/', $view) . '.php');
    }

    /**
     * Set the data to the view.
     *
     * @param array|null $data
     * @return void
     */
    protected function setData(?array $data)
    {
        $this->data = empty($data) ?: $data;
    }

    /**
     * Get the contents of the view instance.
     *
     * @return array
     * @throws NotExistsViewException|ViewEmptyException|ViewInvalidException
     */
    protected function renderContents(): array
    {
        if (!file_exists($this->path)) {
            throw new NotExistsViewException($this->path);
        }

        $response = $this->getContents();

        if (is_null($response)) {
            throw new ViewEmptyException($this->path);
        }

        if (!is_array($response)) {
            throw new ViewInvalidException($this->path);
        }

        return array_merge($response, ['chat_id' => $this->user->uid]);
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @return mixed
     * @throws ViewInvalidException
     */
    protected function getContents()
    {
        try {
            return call_user_func(function ($view) {

                if (is_array($view->data) && !empty($view->data)) {
                    extract($view->data, EXTR_SKIP);
                }

                return require $view->path;

            }, $this);
        } catch (Exception $exception) {
            throw new ViewInvalidException($this->path);
        }
    }
}