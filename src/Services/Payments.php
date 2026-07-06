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

namespace Wekser\Laragram\Services;

use Wekser\Laragram\BotAPI;
use Wekser\Laragram\Telegram\Payments\Invoice;

/**
 * Outbound payment actions that are direct Bot API calls rather than webhook
 * responses: creating shareable invoice links and refunding Telegram Stars.
 *
 * Bound as a singleton under the "laragram.payments" alias.
 *
 *   $link = app('laragram.payments')->invoiceLink(
 *       Invoice::make()->title('Pro')->description('1 month')
 *           ->payload('sub_42')->stars(500)
 *   );
 *
 *   app('laragram.payments')->refund($userId, $chargeId);
 */
class Payments
{
    public function __construct(private readonly BotAPI $api) {}

    /**
     * Create a shareable invoice link (createInvoiceLink).
     *
     * Unlike sendInvoice this does not message a chat — it returns a t.me link
     * that can be shared anywhere; opening it starts the payment flow.
     *
     * @param  Invoice|array<string, mixed> $invoice
     * @return string  The generated invoice link.
     */
    public function invoiceLink(Invoice|array $invoice): string
    {
        $params = $invoice instanceof Invoice ? $invoice->toArray() : $invoice;

        return (string) $this->api->createInvoiceLink($params);
    }

    /**
     * Refund a successful Telegram Stars payment (refundStarPayment).
     *
     * @param  int    $userId   Telegram id of the user whose payment to refund.
     * @param  string $chargeId telegram_payment_charge_id from the successful payment.
     * @return bool             True when Telegram confirms the refund.
     */
    public function refund(int $userId, string $chargeId): bool
    {
        $result = $this->api->refundStarPayment([
            'user_id'                    => $userId,
            'telegram_payment_charge_id' => $chargeId,
        ]);

        return $result === true;
    }

    /**
     * Fetch the bot's Telegram Stars transaction statement (getStarTransactions).
     *
     * @param  int $offset Number of transactions to skip.
     * @param  int $limit  Max transactions to return (1–100).
     * @return array<string, mixed>  The StarTransactions object.
     */
    public function starTransactions(int $offset = 0, int $limit = 100): array
    {
        return (array) $this->api->getStarTransactions([
            'offset' => $offset,
            'limit'  => $limit,
        ]);
    }
}
