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

class WebhookRemoveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laragram:webhook:remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a previously set outgoing webhook';

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
     * @return int
     */
    public function handle()
    {
        try {
            $response = BotAPI::deleteWebhook();
        } catch (\Throwable $e) {
            $this->error('Failed to remove webhook: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($response !== true) {
            $this->error('Failed to remove webhook: ' . ($response['description'] ?? 'Unknown error'));

            return self::FAILURE;
        }

        $this->info('Webhook removed successfully.');

        return self::SUCCESS;
    }
}
