<?php

/*
 * This file is part of Laragram.
 *
 * (c) Sergey Lapin <hello@wekser.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Wekser\Laragram\Console;

use Illuminate\Console\Command;
use Wekser\Laragram\Facades\BotClient;

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

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $url = trim(env('APP_URL'), '/') . '/' . config('laragram.bot.prefix') . '/' . config('laragram.bot.secret');

        if (env('APP_URL') != $_SERVER['APP_URL']) {
            return $this->error('Invalid current APP URL in .env file');
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $this->error('Invalid URL Provided');
        }

        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return $this->error('Invalid URL, should be a HTTPS url');
        }

        $response = BotClient::setWebhook(['url' => $url]);

        if (isset($response['error_code'])) {
            return $this->error($response['description']);
        }

        $this->info('Webhook [' . $url . '] was successfully set!');
    }
}
