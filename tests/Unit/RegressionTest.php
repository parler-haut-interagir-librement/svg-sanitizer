<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Tests\Unit;

use Phil\SvgSanitizer\Internal\Sanitization;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\Removal;
use Phil\SvgSanitizer\RemovalReason;
use Phil\SvgSanitizer\Sanitized;
use Phil\SvgSanitizer\SvgSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Regressions of the bypasses known to SVG sanitizers: an animation that rewrites a link, references padded with
 * zeros, addresses of a special scheme without their authority, and an output that must stay as it is when it is
 * sanitized again.
 */
#[CoversClass(SvgSanitizer::class)]
#[CoversClass(Sanitization::class)]
#[CoversClass(Sanitized::class)]
#[UsesClass(Policy::class)]
#[UsesClass(Removal::class)]
#[UsesClass(RemovalReason::class)]
final class RegressionTest extends TestCase
{
    private const string OPEN = '<svg xmlns="http://www.w3.org/2000/svg">';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function animatedLinks(): iterable
    {
        yield 'set on href' => ['<svg><a><set attributeName="href" to="javascript:alert(1)"/><text>x</text></a></svg>', '<a><text>x</text></a>'];
        yield 'set on xlink:href' => ['<svg xmlns:xlink="http://www.w3.org/1999/xlink"><a><set attributeName="xlink:href" to="javascript:alert(1)"/><text>x</text></a></svg>', '<a><text>x</text></a>'];
        yield 'set in capitals' => ['<svg><a><set attributeName="HREF" to="javascript:alert(1)"/></a></svg>', '<a/>'];
        yield 'set with a space' => ['<svg><a><set attributeName=" href" to="javascript:alert(1)"/><text>x</text></a></svg>', '<a><text>x</text></a>'];
        yield 'animate from' => ['<svg><a><animate attributeName="href" from="javascript:alert(1)" to="#a"/><text>x</text></a></svg>', '<a><text>x</text></a>'];
        yield 'animate values' => ['<svg><a><animate attributeName="href" values="#a;javascript:alert(1)"/><text>x</text></a></svg>', '<a><text>x</text></a>'];
        yield 'animate aimed at a link' => ['<svg><animate href="#t" attributeName="href" to="javascript:alert(1)"/><a id="t"><text>x</text></a></svg>', '<a id="svg-t"><text>x</text></a>'];
        yield 'set on an event' => ['<svg><rect><set attributeName="onclick" to="alert(1)"/></rect></svg>', '<rect/>'];
        yield 'set on a style' => ['<svg><rect><set attributeName="style" to="fill:url(javascript:alert(1))"/></rect></svg>', '<rect/>'];
    }

    /**
     * SMIL may set `href` to any value, after the sanitizer has looked at the attribute: an animation of a link, or
     * of anything that runs or loads, is removed even when the policy keeps animations.
     */
    #[DataProvider('animatedLinks')]
    public function testAnAnimationNeverRewritesALink(string $svg, string $expected): void
    {
        foreach ([new Policy(), new Policy()->withAnimations()] as $policy) {
            self::assertSame(self::OPEN.$expected.'</svg>', new SvgSanitizer($policy)->sanitize($svg)->svg);
        }
    }

    public function testAnAnimationOfAPresentationAttributeIsKeptWhenAnimationsAre(): void
    {
        $svg = '<svg><rect><animate attributeName="opacity" from="0" to="1" dur="1s"/></rect></svg>';

        self::assertSame(self::OPEN.'<rect><animate attributeName="opacity" from="0" to="1" dur="1s"/></rect></svg>', new SvgSanitizer(new Policy()->withAnimations())->sanitize($svg)->svg);
        self::assertSame(self::OPEN.'<rect/></svg>', new SvgSanitizer()->sanitize($svg)->svg);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hiddenSchemes(): iterable
    {
        yield 'a decimal reference padded with zeros' => ['&#0000106;avascript:alert(1)'];
        yield 'a hexadecimal reference padded with zeros' => ['&#x0000006A;avascript:alert(1)'];
        yield 'a tab inside the scheme' => ['jav&#x09;ascript:alert(1)'];
        yield 'a line feed inside the scheme' => ['java&#10;script:alert(1)'];
        yield 'leading spaces' => ['&#32; javascript:alert(1)'];
        yield 'a data address' => ['data:text/html,&lt;script&gt;alert(1)&lt;/script&gt;'];
    }

    #[DataProvider('hiddenSchemes')]
    public function testACharacterReferenceNeverHidesAScheme(string $href): void
    {
        $sanitized = new SvgSanitizer()->sanitize('<svg><a href="'.$href.'"><text>x</text></a></svg>');

        self::assertSame(self::OPEN.'<a><text>x</text></a></svg>', $sanitized->svg);
        self::assertFalse($sanitized->isClean());
    }

    /**
     * A special scheme without `//` is still an address of another host: `https:evil.example/x.png` and
     * `https:/evil.example/x.png` are refused as images, like any address that is not a fragment.
     */
    public function testAnImageOfASpecialSchemeWithoutItsAuthorityIsRefused(): void
    {
        foreach (['https:evil.example/x.png', 'https:/evil.example/x.png', '//evil.example/x.png', 'http:\\\\evil.example\\x.png'] as $href) {
            self::assertSame(self::OPEN.'<image/></svg>', new SvgSanitizer()->sanitize('<svg><image href="'.$href.'"/></svg>')->svg, $href);
        }
    }

    /**
     * Sanitized again, an output keeps everything: nothing more is removed, and without an `id` prefix it comes back
     * byte for byte. With a prefix, each pass writes it once more before the ids and their references, which stay
     * paired.
     */
    public function testAnOutputSanitizedAgainLosesNothing(): void
    {
        $svg = '<svg><defs><linearGradient id="g"/></defs><rect fill="url(#g)" style="fill:url(#g)"/><a href="#g"><text>x &amp; &lt;y&gt;</text></a><set attributeName="href" to="javascript:alert(1)"/></svg>';

        $plain = new SvgSanitizer(new Policy()->withIdPrefix(null)->withAnimations());
        $once = $plain->sanitize($svg);
        self::assertFalse($once->isClean());
        $twice = $plain->sanitize($once->svg);
        self::assertTrue($twice->isClean());
        self::assertSame($once->svg, $twice->svg);

        $prefixed = new SvgSanitizer();
        $again = $prefixed->sanitize($prefixed->sanitize($svg)->svg);
        self::assertTrue($again->isClean());
        self::assertSame(self::OPEN.'<defs><linearGradient id="svg-svg-g"/></defs><rect fill="url(#svg-svg-g)" style="fill: url(&quot;#svg-svg-g&quot;)"/><a href="#svg-svg-g"><text>x &amp; &lt;y&gt;</text></a></svg>', $again->svg);
    }
}
