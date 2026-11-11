<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer;

/**
 * A part of an image the sanitizer removed: why, what — an element or attribute name, an address, a CSS subject, cut
 * to 80 code points —, and on which line of the source it stood.
 */
final readonly class Removal
{
    private const int SUBJECT_LENGTH = 80;

    public string $subject;

    public function __construct(
        public RemovalReason $reason,
        string $subject,
        public int $line = 1,
    ) {
        $this->subject = mb_strlen($subject, 'UTF-8') > self::SUBJECT_LENGTH
            ? mb_substr($subject, 0, self::SUBJECT_LENGTH, 'UTF-8').'…'
            : $subject;
    }
}
