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

namespace Wekser\Laragram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded successful payment (Telegram Stars or fiat).
 *
 * Written by Listeners\RecordPayment when laragram.payments.store is enabled;
 * uniqueness on telegram_payment_charge_id makes recording idempotent (Telegram
 * may redeliver an update). Optional — the table only exists if you publish and
 * run the create_laragram_payments_table migration.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property int|null    $uid
 * @property string      $currency
 * @property int         $total_amount
 * @property string|null $invoice_payload
 * @property string      $telegram_payment_charge_id
 * @property string|null $provider_payment_charge_id
 * @property array       $payload
 */
class Payment extends Model
{
    protected $fillable = [
        'user_id', 'uid', 'currency', 'total_amount', 'invoice_payload',
        'telegram_payment_charge_id', 'provider_payment_charge_id', 'payload',
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'payload'      => 'array',
    ];

    /**
     * Get the configured payments table name.
     */
    public function getTable(): string
    {
        return config('laragram.payments.table', 'laragram_payments');
    }

    /**
     * The user who made the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('laragram.auth.user.model'));
    }

    /** Whether the payment was made in Telegram Stars. */
    public function isStars(): bool
    {
        return $this->currency === 'XTR';
    }
}
