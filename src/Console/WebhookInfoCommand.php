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

namespace Wekser\Laragram\Console;

use Illuminate\Console\Command;
use Wekser\Laragram\Facades\BotAPI;

class WebhookInfoCommand extends Command
{
    protected $signature = 'laragram:webhook:info';

    protected $description = 'Display the current Telegram webhook status';

    public function handle(): int
    {
        try {
            $info = BotAPI::getWebhookInfo();
        } catch (\Throwable $e) {
            $this->error('Could not fetch webhook info: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($info['url'])) {
            $this->warn('No webhook is set. Run php artisan laragram:webhook:set to configure one.');
        }

        $lastErrorDate = isset($info['last_error_date'])
            ? date('Y-m-d H:i:s', $info['last_error_date'])
            : '—';

        $allowedUpdates = !empty($info['allowed_updates'])
            ? implode(', ', $info['allowed_updates'])
            : 'all';

        $this->table(
            ['Key', 'Value'],
            [
                ['URL',             $info['url']                  ?? '—'],
                ['Pending updates', $info['pending_update_count'] ?? 0],
                ['Max connections', $info['max_connections']      ?? 40],
                ['Allowed updates', $allowedUpdates],
                ['IP address',      $info['ip_address']           ?? '—'],
                ['Last error',      $info['last_error_message']   ?? 'None'],
                ['Last error date', $lastErrorDate],
            ]
        );

        return self::SUCCESS;
    }
}
