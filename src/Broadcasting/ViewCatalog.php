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

namespace Wekser\Laragram\Broadcasting;

/**
 * Discovers the on-disk bot views available for broadcasting.
 *
 * A "view" is a directory under resources/{paths.views} that contains at least
 * one recognised component file (text.php or a media component). Names are
 * returned in dot notation — the same form BotResponse::view() accepts — so a
 * nested directory resources/laragram/news/release maps to "news.release".
 *
 * Used to populate the admin panel's view picker; it never renders anything.
 */
final class ViewCatalog
{
    /**
     * Component filenames whose presence marks a directory as a renderable view.
     * Mirrors BotResponse's text + media components (keyboards alone are not a
     * message, so they do not qualify a directory on their own).
     */
    private const COMPONENTS = [
        'text.php', 'photo.php', 'video.php', 'document.php', 'audio.php',
        'voice.php', 'animation.php', 'sticker.php', 'video_note.php', 'media.php',
    ];

    /**
     * All broadcastable view names, dot-notated and sorted alphabetically.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $base = rtrim(resource_path((string) config('laragram.paths.views')), '/');

        if (!is_dir($base)) {
            return [];
        }

        $views = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $entry) {
            if (!$entry->isDir() || !self::isView($entry->getPathname())) {
                continue;
            }

            $relative = trim(substr($entry->getPathname(), strlen($base)), '/');
            $views[]  = str_replace('/', '.', $relative);
        }

        sort($views);

        return $views;
    }

    /**
     * Does the directory hold at least one renderable component file?
     */
    private static function isView(string $dirPath): bool
    {
        foreach (self::COMPONENTS as $component) {
            if (is_file($dirPath . '/' . $component)) {
                return true;
            }
        }

        return false;
    }
}
