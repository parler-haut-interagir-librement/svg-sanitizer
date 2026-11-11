<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer;

/**
 * What the sanitizer kept, as SVG, and what it removed.
 *
 * `svg` is the image to store or write in a page: an `<svg>` element in the SVG namespace, without XML declaration.
 * It is empty when the image was refused whole; the one removal then says why. The removals are listed up to
 * `MAX_REMOVALS`; `omittedRemovals` counts those past them.
 */
final readonly class Sanitized
{
    /** The most removals listed: an image can hold hundreds of thousands, and the others are only counted. */
    public const int MAX_REMOVALS = 100;

    /**
     * @param list<Removal> $removals        the first `MAX_REMOVALS` removals, in the order of the source
     * @param int           $omittedRemovals the removals past the first `MAX_REMOVALS`, counted but not listed
     */
    public function __construct(
        public string $svg,
        public array $removals = [],
        public int $omittedRemovals = 0,
    ) {
    }

    /** Whether nothing was removed: the image was allowed as it was. */
    public function isClean(): bool
    {
        return [] === $this->removals;
    }

    /** Whether the image was refused whole: too large, malformed, not SVG, declaring entities. */
    public function isRefused(): bool
    {
        return '' === $this->svg;
    }
}
