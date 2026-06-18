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

namespace Wekser\Laragram;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Wekser\Laragram\Auth\ArrayAuthDriver;
use Wekser\Laragram\Auth\AuthDriverInterface;
use Wekser\Laragram\Auth\DatabaseAuthDriver;
use Wekser\Laragram\Exceptions\AuthenticationException;
use Wekser\Laragram\Models\User;

/**
 * Authenticates the Telegram sender and exposes the resolved User.
 *
 * Driver selection is injected at construction time via AuthDriverInterface,
 * so adding a new driver requires no changes to this class.
 */
class BotAuth
{
    private ?User $user = null;

    /** Validated sender data extracted from the update payload. */
    private ?array $sender = null;

    private readonly AuthDriverInterface $driverInstance;

    /** Driver name ('database' or 'array') — kept for backward compatibility. */
    private readonly string $driverName;

    /**
     * @param Request            $request
     * @param string             $driverName  'database' or 'array'
     * @param array              $languages   Accepted language codes
     * @param class-string<User> $userModel
     */
    public function __construct(
        private readonly Request $request,
        string                   $driverName,
        private readonly array   $languages,
        string                   $userModel,
    ) {
        $this->driverName     = $driverName;
        $this->driverInstance = match ($driverName) {
            'database' => new DatabaseAuthDriver($userModel),
            'array'    => new ArrayAuthDriver($userModel),
            default    => throw new \InvalidArgumentException(
                "Unknown auth driver: '{$driverName}'. Supported values: 'database', 'array'."
            ),
        };
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Extract, validate and authenticate the Telegram sender.
     *
     * @throws AuthenticationException
     */
    public function authenticate(): static
    {
        $sender = $this->extractSender();

        if ($sender === null) {
            throw new AuthenticationException('No sender information found in the update payload');
        }

        $this->sender = $sender;

        try {
            $this->user = $this->driverInstance->resolveUser($sender, $this->resolveLanguage($sender));
        } catch (\Throwable $e) {
            throw new AuthenticationException('Authentication failed: ' . $e->getMessage(), 0, $e);
        }

        return $this;
    }

    /** Return the authenticated user or null before authenticate() is called. */
    public function user(): ?User
    {
        return $this->user;
    }

    /** Whether a user has been authenticated in this request lifecycle. */
    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    /**
     * Return the driver name ('database' or 'array').
     *
     * @deprecated Use getDriverInstance() to obtain the AuthDriverInterface object.
     */
    public function getDriver(): string
    {
        return $this->driverName;
    }

    /** Return the active authentication driver instance. */
    public function getDriverInstance(): AuthDriverInterface
    {
        return $this->driverInstance;
    }

    /** Return the raw sender data from the update payload. */
    public function getSender(): ?array
    {
        return $this->sender;
    }

    /** Telegram user ID of the sender, or null if not yet authenticated. */
    public function getUserId(): ?int
    {
        return $this->sender['id'] ?? null;
    }

    /** Resolved language code for the sender. */
    public function getUserLanguage(): string
    {
        return $this->resolveLanguage($this->sender ?? []);
    }

    /** Clear the authenticated user and sender (useful in tests or multi-step flows). */
    public function logout(): void
    {
        $this->user   = null;
        $this->sender = null;
    }

    /** Whether the authenticated user is active. */
    public function isUserActive(): bool
    {
        return $this->user !== null && $this->driverInstance->isActive($this->user);
    }

    /** Full name (first + last) of the authenticated user. */
    public function getUserFullName(): string
    {
        if ($this->user === null) {
            return '';
        }

        return trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? ''));
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Find the raw 'from' object in an arbitrary Telegram update payload.
     *
     * Shared with CheckAuth middleware to avoid duplicating the traversal logic.
     */
    public static function findFromInPayload(array $payload): ?array
    {
        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            // Most update types (message, callback_query, inline_query, etc.) use 'from'.
            if (isset($value['from']) && is_array($value['from'])) {
                return $value['from'];
            }

            // poll_answer uses 'user' instead of 'from'.
            if (isset($value['user']) && is_array($value['user'])) {
                return $value['user'];
            }
        }

        return null;
    }

    /**
     * Return true for update types that carry no sender at all (e.g. poll).
     * CheckAuth uses this to let senderless updates pass through.
     */
    public static function isSenderlessPayload(array $payload): bool
    {
        return isset($payload['poll']) && !isset($payload['poll_answer']);
    }

    /**
     * Find the 'from' object inside the raw Telegram update and validate it.
     */
    private function extractSender(): ?array
    {
        $from = static::findFromInPayload($this->request->all());

        return $from !== null ? $this->validateSender($from) : null;
    }

    /**
     * Validate the raw 'from' data and cast to typed scalars.
     *
     * Returns null when required fields (id, first_name) are absent.
     */
    private function validateSender(array $from): ?array
    {
        if (!isset($from['id']) || !is_numeric($from['id'])) {
            return null;
        }

        if (empty($from['first_name'])) {
            return null;
        }

        return [
            'id'            => (int)    $from['id'],
            'first_name'    => (string) $from['first_name'],
            'last_name'     => isset($from['last_name'])     ? (string) $from['last_name']     : null,
            'username'      => isset($from['username'])      ? (string) $from['username']      : null,
            'language_code' => isset($from['language_code']) ? (string) $from['language_code'] : null,
        ];
    }

    /**
     * Pick the best matching language code, falling back to the app locale.
     */
    private function resolveLanguage(array $sender): string
    {
        $code = $sender['language_code'] ?? null;

        return ($code !== null && in_array($code, $this->languages, true))
            ? $code
            : App::getLocale();
    }
}
