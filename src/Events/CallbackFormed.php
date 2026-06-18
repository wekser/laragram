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

use Illuminate\Queue\SerializesModels;
use Wekser\Laragram\Models\User;

class CallbackFormed
{
    use SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public readonly User  $user,
        public readonly array $output,
    ) {}
}
