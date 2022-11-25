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
use Wekser\Laragram\Facades\BotClient;

class GetInfoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected string $signature = 'laragram:getMe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Testing your telegram bot\'s auth token';

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
        $response = BotClient::request('getMe');

        if (isset($response['error_code'])) {
            return $this->error($response['description']);
        }

        $headers = ['id', 'first_name', 'username'];
        $rows = [[$response['id'], $response['first_name'], $response['username']]];

        $this->table($headers, $rows);
    }
}
