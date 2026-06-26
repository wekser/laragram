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

namespace Wekser\Laragram\Support;

/**
 * Detects the update type (e.g. 'message', 'callback_query') from a raw Telegram
 * payload. A Telegram update always carries exactly one type-specific key besides
 * 'update_id'; we prefer the key whose object has a 'from' sender (most types),
 * then fall back to any non-'update_id' array key (poll, poll_answer, …).
 *
 * Shared by Routing\Router and Scene\SceneManager so both agree on the type.
 */
final class UpdateType
{
    /**
     * @param array<string, mixed> $update
     */
    public static function detect(array $update): string
    {
        foreach ($update as $key => $value) {
            if ($key !== 'update_id' && is_array($value) && isset($value['from'])) {
                return (string) $key;
            }
        }

        foreach ($update as $key => $value) {
            if ($key !== 'update_id' && is_array($value)) {
                return (string) $key;
            }
        }

        return '';
    }
}
