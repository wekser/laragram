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

namespace Wekser\Laragram\Exceptions;

use Exception;

/**
 * AuthenticationException is thrown when user authentication fails.
 * 
 * @package Wekser\Laragram\Exceptions
 */
class AuthenticationException extends Exception
{
    /**
     * Default error message.
     */
    protected $message = 'Authentication failed.';

    /**
     * AuthenticationException Constructor.
     *
     * @param string $message The exception message
     * @param int $code The exception code
     * @param Exception|null $previous The previous exception
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null)
    {
        if (empty($message)) {
            $message = $this->message;
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for missing sender information.
     *
     * @return static
     */
    public static function missingSender(): static
    {
        return new static('No sender information found in request');
    }

    /**
     * Create an exception for invalid user data.
     *
     * @param string $field The invalid field name
     * @return static
     */
    public static function invalidUserData(string $field): static
    {
        return new static("Invalid user data: {$field} is required");
    }

    /**
     * Create an exception for database authentication failure.
     *
     * @param string $reason The failure reason
     * @return static
     */
    public static function databaseFailure(string $reason): static
    {
        return new static("Database authentication failed: {$reason}");
    }

    /**
     * Create an exception for user not found.
     *
     * @param int $userId The user ID
     * @return static
     */
    public static function userNotFound(int $userId): static
    {
        return new static("User with ID {$userId} not found");
    }

    /**
     * Create an exception for invalid driver.
     *
     * @param string $driver The invalid driver name
     * @return static
     */
    public static function invalidDriver(string $driver): static
    {
        return new static("Invalid authentication driver: {$driver}");
    }
}





