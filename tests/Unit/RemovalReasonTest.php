<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Tests\Unit;

use Phil\SvgSanitizer\RemovalReason;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemovalReason::class)]
final class RemovalReasonTest extends TestCase
{
    public function testEachReasonIsDescribed(): void
    {
        $descriptions = array_map(static fn (RemovalReason $reason): string => $reason->describe(), RemovalReason::cases());

        self::assertSame([
            'an element that is not allowed',
            'an element that runs code or embeds another document',
            'an attribute that is not allowed',
            'an event handler',
            'a reference to something outside the image',
            'an address that runs code',
            'a link that is not allowed',
            'an animation that is not allowed',
            'CSS that is not allowed',
            'a processing instruction',
            'an image that declares entities',
            'an image that is not well-formed XML',
            'a document that is not an SVG image',
            'an image that is too large',
            'elements nested too deep',
            'an image with too many elements',
            'an element with too many attributes',
        ], $descriptions);
    }

    public function testSomeReasonsRefuseTheWholeImage(): void
    {
        $whole = array_values(array_filter(RemovalReason::cases(), static fn (RemovalReason $reason): bool => $reason->refusesWhole()));

        self::assertSame([RemovalReason::EntityDeclaration, RemovalReason::Malformed, RemovalReason::NotSvg, RemovalReason::TooLarge, RemovalReason::TooDeep, RemovalReason::TooManyElements, RemovalReason::TooManyAttributes], $whole);
    }
}
