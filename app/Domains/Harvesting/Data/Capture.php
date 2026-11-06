<?php

namespace App\Domains\Harvesting\Data;

/**
 * Last capture of a source made by scripts/scrape.cjs: the cleaned HTML, where each <img src>
 * was replaced by a short id ("#img-3"), and the real image URLs by index.
 */
final readonly class Capture
{
    /**
     * @param  string[]  $images
     */
    public function __construct(
        public string $html,
        public array $images = [],
    ) {}

    /**
     * Images are part of the snapshot: an agency may only swap the photo for one stamped "ALQUILADO".
     */
    public function snapshot(): string
    {
        return $this->html.json_encode($this->images);
    }

    /**
     * "#img-3" -> real URL of that image.
     */
    public function imageUrl(mixed $imageId): ?string
    {
        $isImageId = is_string($imageId) && preg_match('/^#img-(\d+)$/', trim($imageId), $matches) === 1;
        if (! $isImageId) {
            return null;
        }

        return $this->images[(int) $matches[1]] ?? null;
    }
}
