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

namespace Wekser\Laragram\Telegram\Payments;

/**
 * Fluent builder for a Telegram invoice (sendInvoice / createInvoiceLink params).
 *
 * Two flavours:
 *
 *   // Telegram Stars (digital goods) — no provider token, one whole-number price.
 *   Invoice::make()
 *       ->title('Pro subscription')
 *       ->description('One month of Pro access')
 *       ->payload('sub_pro_42')
 *       ->stars(500, 'Pro access')
 *       ->toArray();
 *
 *   // Fiat (Telegram Payments 2.0) — provider token + amounts in minor units.
 *   Invoice::make()
 *       ->title('Order #128')
 *       ->description('2 × Margherita')
 *       ->payload('order_128')
 *       ->currency('USD')
 *       ->price('Pizza', 1998)      // 19.98 USD, in cents
 *       ->price('Delivery', 300)
 *       ->providerToken('...')      // or config('laragram.payments.provider_token')
 *       ->needShippingAddress()->flexible()
 *       ->toArray();
 *
 * Pass the result to BotResponse::invoice() (a message) or the Payments service
 * invoiceLink() (a shareable link).
 */
class Invoice
{
    /** Currency code that selects Telegram Stars. */
    public const STARS_CURRENCY = 'XTR';

    private ?string $title       = null;
    private ?string $description = null;
    private ?string $payload     = null;
    private ?string $currency    = null;

    /** @var array<int, array{label: string, amount: int}> */
    private array $prices = [];

    private ?string $providerToken = null;

    private ?string $photoUrl    = null;
    private ?int    $photoWidth  = null;
    private ?int    $photoHeight = null;

    private bool $needName             = false;
    private bool $needPhoneNumber      = false;
    private bool $needEmail            = false;
    private bool $needShippingAddress  = false;
    private bool $sendPhoneToProvider  = false;
    private bool $sendEmailToProvider  = false;
    private bool $isFlexible           = false;

    private ?int $maxTipAmount = null;
    /** @var array<int, int> */
    private array   $suggestedTips  = [];
    private ?string $startParameter = null;
    /** @var array<string, mixed>|null */
    private ?array $providerData = null;

    private function __construct() {}

    /** Create a new invoice builder. */
    public static function make(): static
    {
        return new static();
    }

    // -------------------------------------------------------------------------
    // Core fields
    // -------------------------------------------------------------------------

    /** Product name (1–32 characters). */
    public function title(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    /** Product description (1–255 characters). */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Bot-defined invoice payload (1–128 bytes). Not shown to the user; echoed
     * back in pre_checkout_query and the successful_payment message so you can
     * reconcile the order.
     */
    public function payload(string $payload): static
    {
        $this->payload = $payload;
        return $this;
    }

    /** Three-letter ISO 4217 currency code (e.g. 'USD'), or 'XTR' for Stars. */
    public function currency(string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * Add a price line item.
     *
     * @param string $label  Portion label shown to the user.
     * @param int    $amount Price in the currency's smallest unit (e.g. cents for
     *                       USD; for Stars this is a whole number of stars).
     */
    public function price(string $label, int $amount): static
    {
        $this->prices[] = ['label' => $label, 'amount' => $amount];
        return $this;
    }

    /**
     * Shortcut for a Telegram Stars invoice: sets currency to XTR and defines
     * the single required price line (toArray() sends an empty provider token
     * for Stars).
     *
     * @param int    $amount Number of Stars.
     * @param string $label  Label for the price line shown to the user.
     */
    public function stars(int $amount, string $label = 'Total'): static
    {
        $this->currency = self::STARS_CURRENCY;
        $this->prices   = [['label' => $label, 'amount' => $amount]];
        return $this;
    }

    /** Payment provider token from @BotFather (fiat only; ignored for Stars). */
    public function providerToken(string $token): static
    {
        $this->providerToken = $token;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Optional fields
    // -------------------------------------------------------------------------

    /** Product photo shown in the invoice. */
    public function photo(string $url, ?int $width = null, ?int $height = null): static
    {
        $this->photoUrl    = $url;
        $this->photoWidth  = $width;
        $this->photoHeight = $height;
        return $this;
    }

    /** Require the user's full name to complete the order. */
    public function needName(bool $need = true): static
    {
        $this->needName = $need;
        return $this;
    }

    /** Require the user's phone number to complete the order. */
    public function needPhoneNumber(bool $need = true): static
    {
        $this->needPhoneNumber = $need;
        return $this;
    }

    /** Require the user's email to complete the order. */
    public function needEmail(bool $need = true): static
    {
        $this->needEmail = $need;
        return $this;
    }

    /** Require the user's shipping address to complete the order. */
    public function needShippingAddress(bool $need = true): static
    {
        $this->needShippingAddress = $need;
        return $this;
    }

    /** Forward the collected phone number to the payment provider. */
    public function sendPhoneNumberToProvider(bool $send = true): static
    {
        $this->sendPhoneToProvider = $send;
        return $this;
    }

    /** Forward the collected email to the payment provider. */
    public function sendEmailToProvider(bool $send = true): static
    {
        $this->sendEmailToProvider = $send;
        return $this;
    }

    /**
     * Mark the final price as depending on the shipping method. Telegram will
     * send a shipping_query so the bot can return shipping options.
     */
    public function flexible(bool $flexible = true): static
    {
        $this->isFlexible = $flexible;
        return $this;
    }

    /** Maximum accepted tip in the currency's smallest unit. */
    public function maxTip(int $amount): static
    {
        $this->maxTipAmount = $amount;
        return $this;
    }

    /**
     * Suggested tip amounts (smallest units), at most 4, positive and increasing.
     *
     * @param array<int, int> $amounts
     */
    public function suggestedTips(array $amounts): static
    {
        $this->suggestedTips = array_values($amounts);
        return $this;
    }

    /** Deep-linking parameter attached to the "pay via bot" forwarded message. */
    public function startParameter(string $parameter): static
    {
        $this->startParameter = $parameter;
        return $this;
    }

    /**
     * Provider-specific JSON data (serialized to a JSON string for the API).
     *
     * @param array<string, mixed> $data
     */
    public function providerData(array $data): static
    {
        $this->providerData = $data;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Output
    // -------------------------------------------------------------------------

    /**
     * Build the parameter array for sendInvoice / createInvoiceLink.
     *
     * Fiat invoices fall back to config('laragram.payments.currency') and
     * config('laragram.payments.provider_token') when currency / provider token
     * are not set explicitly.
     *
     * @return array<string, mixed>
     *
     * @throws \InvalidArgumentException When a required field is missing or the
     *                                   Stars constraints are violated.
     */
    public function toArray(): array
    {
        $this->requireField('title', $this->title);
        $this->requireField('description', $this->description);
        $this->requireField('payload', $this->payload);

        $currency = $this->currency ?? (string) config('laragram.payments.currency', 'USD');

        if ($currency === '') {
            throw new \InvalidArgumentException('Invoice currency is required.');
        }

        if (empty($this->prices)) {
            throw new \InvalidArgumentException('An invoice needs at least one price line — call price() or stars().');
        }

        $isStars = $currency === self::STARS_CURRENCY;

        if ($isStars) {
            // Telegram requires exactly one price line for Stars invoices.
            if (count($this->prices) !== 1) {
                throw new \InvalidArgumentException('A Telegram Stars invoice must contain exactly one price line.');
            }

            $providerToken = '';
        } else {
            $providerToken = $this->providerToken ?? (string) config('laragram.payments.provider_token', '');

            if ($providerToken === '') {
                throw new \InvalidArgumentException(
                    'A provider_token is required for fiat invoices. Set ->providerToken() or config laragram.payments.provider_token.'
                );
            }
        }

        return array_filter([
            'title'                         => $this->title,
            'description'                   => $this->description,
            'payload'                       => $this->payload,
            'provider_token'                => $providerToken,
            'currency'                      => $currency,
            'prices'                        => $this->prices,
            'max_tip_amount'                => $this->maxTipAmount,
            'suggested_tip_amounts'         => $this->suggestedTips ?: null,
            'start_parameter'               => $this->startParameter,
            'provider_data'                 => $this->providerData !== null ? json_encode($this->providerData) : null,
            'photo_url'                     => $this->photoUrl,
            'photo_width'                   => $this->photoWidth,
            'photo_height'                  => $this->photoHeight,
            'need_name'                     => $this->needName ?: null,
            'need_phone_number'             => $this->needPhoneNumber ?: null,
            'need_email'                    => $this->needEmail ?: null,
            'need_shipping_address'         => $this->needShippingAddress ?: null,
            'send_phone_number_to_provider' => $this->sendPhoneToProvider ?: null,
            'send_email_to_provider'        => $this->sendEmailToProvider ?: null,
            'is_flexible'                   => $this->isFlexible ?: null,
        ], static fn ($v) => $v !== null);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function requireField(string $name, ?string $value): void
    {
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException("Invoice {$name} is required.");
        }
    }
}
