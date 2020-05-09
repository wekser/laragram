<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <me@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Console;

use Illuminate\Console\Command;
use Wekser\Laragram\BotApi;

class SetWebhookCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laragram:setWebhook';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Specify a url and receive incoming updates via an outgoing webhook';

    protected $appUrl;

    protected $botPrefix;

    protected $botSecret;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->appUrl = config('app.url');
        $this->botPrefix = config('laragram.bot.prefix');
        $this->botSecret = config('laragram.bot.secret');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->appUrl != $_SERVER['APP_URL']) {
            return $this->error('Invalid current APP URL in .env file');
        }

        if (parse_url($this->appUrl, PHP_URL_SCHEME) !== 'https') {
            return $this->error('Invalid URL, should be an HTTPS url');
        }

        $url = implode('/', [trim($this->appUrl, '/'), $this->botPrefix, $this->botSecret]);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $this->error('Invalid URL Provided');
        }

        $response = (new BotApi())->setWebhook(['url' => $url]);

        if (isset($response['error_code'])) {
            return $this->error($response['description']);
        }

        $this->info("Webhook [$url] was successfully set!");
    }
}
