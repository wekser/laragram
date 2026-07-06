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

namespace Wekser\Laragram\Listeners;

use Wekser\Laragram\Events\PaymentReceived;
use Wekser\Laragram\Models\Payment;

/**
 * Persists a received payment to the laragram_payments table.
 *
 * Bound to PaymentReceived. Opt-in via laragram.payments.store (default off,
 * since it needs the published migration) and requires the "database" driver.
 * Uniqueness on telegram_payment_charge_id (via updateOrCreate) makes it
 * idempotent when Telegram redelivers an update. Must never throw — recording
 * history is best-effort and must not break payment processing.
 */
class RecordPayment
{
    public function handle(PaymentReceived $event): void
    {
        if (! config('laragram.payments.store', false)) {
            return;
        }

        if (config('laragram.auth.driver') !== 'database') {
            return;
        }

        $chargeId = $event->chargeId();

        if (empty($chargeId)) {
            return;
        }

        try {
            $this->model()::updateOrCreate(
                ['telegram_payment_charge_id' => $chargeId],
                [
                    'user_id'                    => $event->user?->id,
                    'uid'                        => $event->user?->uid,
                    'currency'                   => $event->currency(),
                    'total_amount'               => $event->totalAmount(),
                    'invoice_payload'            => $event->invoicePayload(),
                    'provider_payment_charge_id' => $event->payment['provider_payment_charge_id'] ?? null,
                    'payload'                    => $event->payment,
                ],
            );
        } catch (\Throwable $e) {
            // Recording history must never turn a successful payment into a failure,
            // but log it — otherwise a misconfiguration (payments.store enabled
            // without the payments migration) silently drops all history forever.
            app('log')->error('laragram: failed to record payment', [
                'charge_id' => $chargeId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    protected function model(): string
    {
        return config('laragram.payments.model', Payment::class);
    }
}
