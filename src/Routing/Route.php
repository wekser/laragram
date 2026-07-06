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

namespace Wekser\Laragram\Routing;

/**
 * Immutable Value Object representing a single bot route.
 */
final class Route
{
    /**
     * @param string              $event      Telegram update type (message, callback_query, …)
     * @param string              $listener   Dot-path to the matching field inside the update object
     * @param array|null          $from       Station(s) the user must be at for this route to match
     * @param array|null          $contains   Parsed pattern entries from RouteCollection::contains()
     * @param string|null         $controller FQCN of the controller class
     * @param string|null         $method     Method name on the controller
     * @param callable|null       $callback   Closure used when no controller/method is set
     * @param string|null         $name       Optional route name for introspection
     * @param array|null          $roles      Role(s) the user must have for this route to match
     * @param array|null          $chatTypes  Chat type(s) the update must originate from (private/group/…)
     */
    public function __construct(
        public readonly string    $event,
        public readonly string    $listener,
        public readonly ?array    $from       = null,
        public readonly ?array    $contains   = null,
        public readonly ?string   $controller = null,
        public readonly ?string   $method     = null,
        public readonly mixed     $callback   = null,
        public readonly ?string   $name       = null,
        public readonly ?array    $roles      = null,
        public readonly ?array    $chatTypes  = null,
    ) {}

    /**
     * Whether this route is handled by a controller class.
     */
    public function hasController(): bool
    {
        return $this->controller !== null && $this->method !== null;
    }

    /**
     * Whether this route is handled by a closure.
     */
    public function hasCallback(): bool
    {
        return $this->callback !== null;
    }

    /**
     * Convert to legacy array format for backward-compatibility with
     * BotRouter and FormRequest which still read plain arrays.
     *
     * @internal
     */
    public function toArray(): array
    {
        return array_filter([
            'event'      => $this->event,
            'listener'   => $this->listener,
            'from'       => $this->from,
            'contains'   => $this->contains,
            'controller' => $this->controller,
            'method'     => $this->method,
            'callback'   => $this->callback,
            'name'       => $this->name,
            'roles'      => $this->roles,
            'chat_types' => $this->chatTypes,
        ], fn ($v) => $v !== null);
    }

    /**
     * Reconstruct from a legacy array (e.g. from BotRouteCollection).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            event:      $data['event'],
            listener:   $data['listener'],
            from:       isset($data['from']) ? (array) $data['from'] : null,
            contains:   $data['contains']   ?? null,
            controller: $data['controller'] ?? null,
            method:     $data['method']     ?? null,
            callback:   $data['callback']   ?? null,
            name:       $data['name']       ?? null,
            roles:      isset($data['roles']) ? (array) $data['roles'] : null,
            chatTypes:  isset($data['chat_types']) ? (array) $data['chat_types'] : null,
        );
    }
}
