<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Exception;

use InvalidArgumentException;

/**
 * A `Policy` was asked to allow what the library never allows, or was given a limit that cannot hold.
 */
final class InvalidPolicy extends InvalidArgumentException implements SvgSanitizerException
{
    public static function deniedElement(string $element): self
    {
        return new self(\sprintf('The element "%s" can run code, embed another document or load a resource; it is never allowed.', $element));
    }

    public static function deniedAttribute(string $attribute): self
    {
        return new self(\sprintf('The attribute "%s" can run code or change where references point; it is never allowed.', $attribute));
    }

    public static function deniedScheme(string $scheme): self
    {
        return new self(\sprintf('The scheme "%s:" can run code or hold a document; links may never use it.', $scheme));
    }

    public static function limit(string $name, int $value): self
    {
        return new self(\sprintf('The %s must be at least 1; got %d.', $name, $value));
    }
}
