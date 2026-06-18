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

namespace Wekser\Laragram\View;

/**
 * Accumulates media items during the evaluation of a media.php component.
 * Produces the 'media' array for Telegram's sendMediaGroup method.
 */
final class MediaGroupState
{
    private array $items = [];

    public function addPhoto(string $fileId, ?string $caption = null): void
    {
        $this->addItem('photo', $fileId, $caption);
    }

    public function addVideo(string $fileId, ?string $caption = null): void
    {
        $this->addItem('video', $fileId, $caption);
    }

    private function addItem(string $type, string $media, ?string $caption): void
    {
        $item = ['type' => $type, 'media' => $media];

        if ($caption !== null) {
            $item['caption'] = $caption;
        }

        $this->items[] = $item;
    }

    public function toArray(): array
    {
        return $this->items;
    }
}
