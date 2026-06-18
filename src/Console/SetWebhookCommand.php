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
use Wekser\Laragram\Facades\BotAPI;

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
        $this->botPrefix = config('laragram.telegram.prefix');
        $this->botSecret = config('laragram.telegram.secret');
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (parse_url($this->appUrl, PHP_URL_SCHEME) !== 'https') return $this->error('Invalid URL, should be an HTTPS url');

        if (empty($this->botSecret) || !preg_match('/^[A-Za-z0-9_-]{8,}$/', (string) $this->botSecret)) {
            return $this->error('Invalid or empty webhook secret. Please set LARAGRAM_WEBHOOK_SECRET in .env');
        }

        $url = implode('/', [trim($this->appUrl, '/'), $this->botPrefix, $this->botSecret]);

        if (filter_var($url, FILTER_VALIDATE_URL) === false) return $this->error('Invalid URL Provided');

        $response = BotAPI::setWebhook(['url' => $url]);

        if (isset($response['error_code'])) return $this->error($response['description']);

        $this->info("Webhook [$url] was successfully set!");
    }
}
