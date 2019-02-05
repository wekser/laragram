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
     * Callback Constructor
     *
     * @param array $config
     * @return void
     */
    public function __construct(array $config)
    {
        $this->viewsPath = array_get($config, 'path', 'laragram');
    }

    /**
     * Set the state to next user request.
     *
     * @param string $state
     * @return $this
     */
    public function redirect(string $state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * Get the contents of the view.
     *
     * @param  string $view
     * @param  array|null $data
     * @return $this
     */
    public function view(string $view, $data = [])
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
    protected function render(string $view, $data = []): array
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
    protected function setPath($view)
    {
        $this->path = resource_path($this->viewsPath . '/' . str_replace('.', '/', $view) . '.php');
    }

    /**
     * Set the data to the view.
     *
     * @param array $data
     * @return void
     */
    protected function setData(array $data)
    {
        $this->data = empty($data) ?: $data;
    }

    /**
     * Get the contents of the view instance.
     *
     * @return array
     * @throws Exception
     */
    protected function renderContents()
    {
        if (!file_exists($this->path)) {
            throw new Exception('The [' . $this->path . '] view not exists.', 500);
        }

        $response = $this->getContents();

        if (is_null($response)) {
            throw new Exception('The [' . $this->path . '] view is empty.', 500);
        }

        if (!is_array($response)) {
            throw new Exception('Incorrect response from route method.', 500);
        }

        return $response;
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @return mixed
     * @throws Exception
     */
    protected function getContents()
    {
        $user = BotAuth::user();

        try {
            return call_user_func(function ($view) use ($user) {

                if (is_array($view->data) && !empty($view->data)) {
                    extract($view->data, EXTR_SKIP);
                }

                return require $view->path;

            }, $this);
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage(), $exception->getCode());
        }
    }
}