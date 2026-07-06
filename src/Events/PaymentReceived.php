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

namespace Wekser\Laragram\Events;

use Wekser\Laragram\Models\User;

/**
 * Fired whenever a successful payment (Telegram Stars or fiat) is received.
 *
 * Telegram delivers a completed payment as a `successful_payment` field on a
 * message update. Laragram detects it centrally in the processing pipeline and
 * fires this event — independently of whether the host defined a route for it —
 * so an application can grant the purchased entitlement from a single listener:
 *
 *   Event::listen(PaymentReceived::class, function ($event) {
 *       Subscription::activate($event->user, $event->payload());
 *   });
 *
 * The bundled Listeners\RecordPayment persists every payment to laragram_payments
 * (idempotently) when laragram.payments.store is enabled. Listening is optional.
 */
class PaymentReceived
{
    /**
     * @param User|null            $user    The paying user (null under the array driver / unresolved).
     * @param array<string, mixed> $payment The raw successful_payment object from Telegram.
     */
    public function __construct(
        public readonly ?User $user,
        public readonly array $payment,
    ) {}

    /** The bot-defined invoice payload echoed back by Telegram. */
    public function invoicePayload(): ?string
    {
        return $this->payment['invoice_payload'] ?? null;
    }

    /** The Telegram payment charge id (unique per payment; needed for refunds). */
    public function chargeId(): ?string
    {
        return $this->payment['telegram_payment_charge_id'] ?? null;
    }

    /** Total amount in the currency's smallest unit (whole Stars for XTR). */
    public function totalAmount(): int
    {
        return (int) ($this->payment['total_amount'] ?? 0);
    }

    /** Three-letter currency code, or 'XTR' for Telegram Stars. */
    public function currency(): ?string
    {
        return $this->payment['currency'] ?? null;
    }

    /** Whether this payment was made in Telegram Stars. */
    public function isStars(): bool
    {
        return $this->currency() === 'XTR';
    }

    /** Alias for the raw payment object. */
    public function payload(): array
    {
        return $this->payment;
    }
}
