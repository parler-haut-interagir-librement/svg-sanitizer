<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Tests\Unit;

use Phil\CssSanitizer\Policy as CssPolicy;
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

#[CoversClass(SvgSanitizer::class)]
#[CoversClass(Sanitization::class)]
#[CoversClass(Sanitized::class)]
#[CoversClass(Removal::class)]
#[UsesClass(Policy::class)]
#[UsesClass(RemovalReason::class)]
final class SvgSanitizerTest extends TestCase
{
    private const string OPEN = '<svg xmlns="http://www.w3.org/2000/svg">';

    private const string EMPTY = '<svg xmlns="http://www.w3.org/2000/svg"/>';

    public function testACleanImageIsWrittenAgain(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" width="10"><defs><linearGradient id="g"><stop offset="0%" stop-color="#fff"/></linearGradient></defs>'
            .'<rect width="5" height="5" fill="url(#g)" stroke="red" transform="rotate(45 5 5)"/><text x="1" font-family="\'Open Sans\'">A &amp; B &lt; C</text></svg>';

        $sanitized = new SvgSanitizer()->sanitize($svg);

        self::assertSame(str_replace(['id="g"', 'url(#g)'], ['id="svg-g"', 'url(#svg-g)'], $svg), $sanitized->svg);
        self::assertSame($svg, new SvgSanitizer(new Policy()->withIdPrefix(null))->sanitize($svg)->svg);
        self::assertTrue($sanitized->isClean());
        self::assertFalse($sanitized->isRefused());
    }

    public function testTheDeclarationTheDocumentTypeAndTheCommentsAreLeftOut(): void
    {
        $sanitized = new SvgSanitizer()->sanitize("\u{FEFF}<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!DOCTYPE svg PUBLIC \"-//W3C//DTD SVG 1.1//EN\" \"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd\">\n<!-- made by hand --><svg><!-- inside --><g><![CDATA[a < b]]></g></svg>");

        self::assertSame(self::OPEN.'<g>a &lt; b</g></svg>', $sanitized->svg);
        self::assertTrue($sanitized->isClean());
    }

    public function testAnImageWithoutNamespaceIsWrittenInTheNamespaceOfSvg(): void
    {
        self::assertSame(self::OPEN.'<circle r="1"/></svg>', new SvgSanitizer()->sanitize('<svg><circle r="1"/></svg>')->svg);
    }

    /**
     * @return iterable<string, array{string, string, list<array{RemovalReason, string, int}>}>
     */
    public static function images(): iterable
    {
        yield 'script and foreignObject' => [
            "<svg>\n<script>alert(1)</script>\n<foreignObject><div xmlns=\"http://www.w3.org/1999/xhtml\">x</div></foreignObject><g/></svg>",
            self::OPEN."\n\n<g/></svg>",
            [[RemovalReason::DeniedElement, 'script', 2], [RemovalReason::DeniedElement, 'foreignObject', 3]],
        ];
        yield 'elements not allowed and their content' => [
            '<svg><metadata><title>x</title></metadata><animate attributeName="x"/><g attributeName="href"/></svg>',
            self::OPEN.'<g/></svg>',
            [[RemovalReason::Element, 'metadata', 1], [RemovalReason::Element, 'animate', 1], [RemovalReason::Attribute, 'attributeName', 1]],
        ];
        yield 'elements of another namespace' => [
            '<svg xmlns:i="urn:i"><i:named><rect/></i:named><i:x/></svg>',
            self::EMPTY,
            [[RemovalReason::Element, 'i:named', 1], [RemovalReason::Element, 'i:x', 1]],
        ];
        yield 'event handlers' => [
            '<svg onload="alert(1)"><rect ONCLICK="x" onmouseover="y"/></svg>',
            self::OPEN.'<rect/></svg>',
            [[RemovalReason::EventHandler, 'onload', 1], [RemovalReason::EventHandler, 'ONCLICK', 1], [RemovalReason::EventHandler, 'onmouseover', 1]],
        ];
        yield 'attributes not allowed and of other namespaces' => [
            '<svg xmlns:i="urn:i" xml:base="http://x/" i:v="1" data-x="1" xml:lang="fr" xml:space="preserve"><g u:x="1"/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg" xml:lang="fr" xml:space="preserve"><g/></svg>',
            [[RemovalReason::Attribute, 'xml:base', 1], [RemovalReason::Attribute, 'i:v', 1], [RemovalReason::Attribute, 'data-x', 1], [RemovalReason::Attribute, 'u:x', 1]],
        ];
        yield 'references inside the image' => [
            '<svg xmlns:xlink="http://www.w3.org/1999/xlink"><use href=" #a "/><use xlink:href="#b"/><pattern href="#"/></svg>',
            '<svg xmlns="http://www.w3.org/2000/svg"><use href="#svg-a"/><use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-b"/><pattern href="#"/></svg>',
            [],
        ];
        yield 'references outside the image' => [
            "<svg xmlns:xlink=\"http://www.w3.org/1999/xlink\"><use href=\"other.svg#a\"/><use xlink:href=\"https://x.test/a.svg#b\"/><image href=\"photo.png\"/><feImage href=\"//x.test/p.png\"/>\n<textPath href=\"data:text/plain,x\">t</textPath></svg>",
            self::OPEN.'<use/><use/><image/><feImage/>'."\n".'<textPath>t</textPath></svg>',
            [[RemovalReason::ExternalReference, 'other.svg#a', 1], [RemovalReason::ExternalReference, 'https://x.test/a.svg#b', 1], [RemovalReason::ExternalReference, 'photo.png', 1], [RemovalReason::ExternalReference, '//x.test/p.png', 1], [RemovalReason::DangerousUrl, 'data:text/plain,x', 2]],
        ];
        yield 'raster images in data addresses' => [
            '<svg><image href="data:image/png;base64,iVBO"/><image href="DATA:image/JPEG,x"/><feImage href="data:image/webp;base64,U"/><image href="data:image/gif,G"/><image href="data:image/avif,A"/><image href="data:image/svg+xml,&lt;svg/&gt;"/><image href="data:image/pngx,x"/><use href="data:image/png,x"/></svg>',
            self::OPEN.'<image href="data:image/png;base64,iVBO"/><image href="DATA:image/JPEG,x"/><feImage href="data:image/webp;base64,U"/><image href="data:image/gif,G"/><image href="data:image/avif,A"/><image/><image/><use/></svg>',
            [[RemovalReason::DangerousUrl, 'data:image/svg+xml,<svg/>', 1], [RemovalReason::DangerousUrl, 'data:image/pngx,x', 1], [RemovalReason::DangerousUrl, 'data:image/png,x', 1]],
        ];
        yield 'links' => [
            '<svg><a href="https://x.test/">a</a><a href="MAILTO:a@x.test">b</a><a href="#top">c</a><a href="java&#9;script:alert(1)">d</a><a href=" JavaScript:x">e</a><a href="ftp://x.test/">f</a><a href="page.html">g</a><a href="vbscript:x">h</a><a href="page.html?x=javascript:1">i</a></svg>',
            self::OPEN.'<a href="https://x.test/">a</a><a href="MAILTO:a@x.test">b</a><a href="#svg-top">c</a><a>d</a><a>e</a><a>f</a><a>g</a><a>h</a><a>i</a></svg>',
            [[RemovalReason::DangerousUrl, "java\tscript:alert(1)", 1], [RemovalReason::DangerousUrl, 'JavaScript:x', 1], [RemovalReason::Link, 'ftp://x.test/', 1], [RemovalReason::Link, 'page.html', 1], [RemovalReason::DangerousUrl, 'vbscript:x', 1], [RemovalReason::Link, 'page.html?x=javascript:1', 1]],
        ];
        yield 'style attributes' => [
            '<svg><rect style="fill: red; position: fixed; background: url(x.png)"/><rect style="behavior: url(x.htc)"/><rect style="fill: u\72l(http://x.test/)"/></svg>',
            self::OPEN.'<rect style="fill: red"/><rect/><rect/></svg>',
            [[RemovalReason::Style, 'style: position', 1], [RemovalReason::ExternalReference, 'style: x.png', 1], [RemovalReason::Style, 'style: behavior', 1], [RemovalReason::ExternalReference, 'style: http://x.test/', 1]],
        ];
        yield 'presentation attributes' => [
            '<svg><rect fill="url(http://x.test/#p)" stroke="url(&quot;#g&quot;) blue" mask="url(javascript:x)" stroke-dasharray="5, 5" opacity=".5" filter="url(#f)" color="red; position: fixed" fill-opacity=""/><linearGradient gradientTransform="matrix(1 0 0 1 2 2)" patternTransform="expression(x)"/></svg>',
            self::OPEN.'<rect stroke="url(&quot;#svg-g&quot;) blue" stroke-dasharray="5, 5" opacity=".5" filter="url(#svg-f)" fill-opacity=""/><linearGradient gradientTransform="matrix(1 0 0 1 2 2)"/></svg>',
            [[RemovalReason::ExternalReference, 'fill: http://x.test/#p', 1], [RemovalReason::DangerousUrl, 'mask: javascript:x', 1], [RemovalReason::Style, 'color: position', 1], [RemovalReason::Style, 'patternTransform: expression()', 1]],
        ];
        $styled = "<svg><style type=\"text/css\" media=\"print\"><![CDATA[\n@import url(x.css);\nrect { fill: red; cursor: url(x.cur) }\n]]></style><style>@import 'y.css';</style><rect/></svg>";
        $id = 'svg-drawing-'.substr(hash('sha256', $styled), 0, 16);
        yield 'style elements' => [
            $styled,
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$id.'"><style type="text/css">#'.$id." rect {\n  fill: red;\n}\n</style><rect/></svg>",
            [[RemovalReason::Attribute, 'media', 1], [RemovalReason::Style, 'style: @import', 1], [RemovalReason::Style, 'style: cursor', 1], [RemovalReason::Style, 'style: @import', 4]],
        ];
        yield 'processing instructions' => [
            "<?xml-stylesheet href=\"x.css\"?>\n<svg><?php echo 1; ?><g><?x y?></g></svg>",
            self::OPEN.'<g/></svg>',
            [[RemovalReason::ProcessingInstruction, 'xml-stylesheet', 1], [RemovalReason::ProcessingInstruction, 'php', 2], [RemovalReason::ProcessingInstruction, 'x', 2]],
        ];
    }

    /**
     * @param list<array{RemovalReason, string, int}> $removals
     */
    #[DataProvider('images')]
    public function testItRemovesWhatIsNotAllowed(string $svg, string $expected, array $removals): void
    {
        $sanitized = new SvgSanitizer(new Policy()->withElements('style'))->sanitize($svg);

        self::assertSame($expected, $sanitized->svg);
        self::assertSame($removals, $this->removals($sanitized));
        self::assertSame([] === $removals, $sanitized->isClean());
    }

    public function testAnAnimationOfAReferenceAStyleOrAHandlerIsRemoved(): void
    {
        $sanitized = new SvgSanitizer(new Policy()->withAnimations())->sanitize('<svg><a><set attributeName="href" to="javascript:alert(1)"/><animate attributeName=" XLINK:HREF " values="#x"/><set attributeName="onclick" to="x"/><set attributeName="style" to="x"/><set attributeName="xml:base" to="x"/><set attributeName="mxonx" to="x"/></a></svg>');

        self::assertSame(self::OPEN.'<a><set attributeName="mxonx" to="x"/></a></svg>', $sanitized->svg);
        self::assertSame([[RemovalReason::Animation, 'set href', 1], [RemovalReason::Animation, 'animate xlink:href', 1], [RemovalReason::Animation, 'set onclick', 1], [RemovalReason::Animation, 'set style', 1], [RemovalReason::Animation, 'set xml:base', 1]], $this->removals($sanitized));
    }

    public function testAnimationsAreKeptWhenThePolicyAllowsThem(): void
    {
        $sanitizer = new SvgSanitizer(new Policy()->withAnimations());
        $sanitized = $sanitizer->sanitize('<svg><rect><animate attributeName="x" from="0" to="10" dur="2s" values="0;5;10" begin="click"/><set attributeName="fill" to="url(http://x.test/)"/><animate attributeName="fill" by="u\72l(x)"/><animate attributeName="fill" values="x:y"/><animateMotion path="M0 0 L 10 10"><mpath href="#p"/></animateMotion></rect><feColorMatrix values="1 0 0"/></svg>');

        self::assertSame(self::OPEN.'<rect><animate attributeName="x" from="0" to="10" dur="2s" values="0;5;10" begin="click"/><set attributeName="fill"/><animate attributeName="fill"/><animate attributeName="fill"/><animateMotion path="M0 0 L 10 10"><mpath href="#svg-p"/></animateMotion></rect><feColorMatrix values="1 0 0"/></svg>', $sanitized->svg);
        self::assertSame([[RemovalReason::Animation, 'to: url(http://x.test/)', 1], [RemovalReason::Animation, 'by: u\72l(x)', 1], [RemovalReason::Animation, 'values: x:y', 1]], $this->removals($sanitized));
        self::assertSame(self::OPEN.'<feColorMatrix values="a:b(c)"/></svg>', $sanitizer->sanitize('<svg><feColorMatrix values="a:b(c)"/></svg>')->svg);
    }

    public function testThePolicyDecides(): void
    {
        $policy = new Policy()
            ->withoutElements('text')
            ->withoutAttributes('class')
            ->withAttributes('data-name')
            ->withLinkSchemes('ftp')
            ->withDataImages(false)
            ->withCss(new CssPolicy()->withoutProperties('color'));
        $sanitized = new SvgSanitizer()->withPolicy($policy)->sanitize('<svg><text>x</text><g class="a" data-name="b" style="color: red"/><a href="ftp://x.test/"/><a href="https://x.test/"/><image href="data:image/png,x"/></svg>');

        self::assertSame(self::OPEN.'<g data-name="b"/><a href="ftp://x.test/"/><a/><image/></svg>', $sanitized->svg);
        self::assertSame([[RemovalReason::Element, 'text', 1], [RemovalReason::Attribute, 'class', 1], [RemovalReason::Style, 'style: color', 1], [RemovalReason::Link, 'https://x.test/', 1], [RemovalReason::DangerousUrl, 'data:image/png,x', 1]], $this->removals($sanitized));
    }

    public function testAnImageWhoseRootIsNotAllowedIsRefused(): void
    {
        $sanitized = new SvgSanitizer(new Policy()->withoutElements('svg'))->sanitize('<svg/>');

        self::assertTrue($sanitized->isRefused());
        self::assertSame([[RemovalReason::Element, 'svg', 1]], $this->removals($sanitized));
    }

    /**
     * @return iterable<string, array{string, Policy, RemovalReason, string}>
     */
    public static function refusals(): iterable
    {
        yield 'too large' => ['<svg/>', new Policy(maxBytes: 5), RemovalReason::TooLarge, '6 bytes'];
        yield 'entities' => ['<!DOCTYPE svg [<!entity a "aaaa">]><svg>&a;</svg>', new Policy(), RemovalReason::EntityDeclaration, '<!ENTITY'];
        yield 'malformed' => ['<svg><g></svg>', new Policy(), RemovalReason::Malformed, '14 bytes'];
        yield 'empty' => ['', new Policy(), RemovalReason::Malformed, '0 bytes'];
        yield 'null byte' => ["<svg>\0</svg>", new Policy(), RemovalReason::Malformed, '12 bytes'];
        yield 'utf-32, where the parser warns' => ["<\0\0\0s\0\0\0v\0\0\0g\0\0\0/\0\0\0>\0\0\0", new Policy(), RemovalReason::Malformed, '24 bytes'];
        yield 'entities in utf-16' => [mb_convert_encoding('<!DOCTYPE svg [<!ENTITY a "aaaa">]><svg>&a;</svg>', 'UTF-16LE', 'UTF-8'), new Policy(), RemovalReason::Malformed, '98 bytes'];
        yield 'not utf-8' => ["<svg><text>\xE9</text></svg>", new Policy(), RemovalReason::Malformed, '25 bytes'];
        yield 'not svg' => ['<html><svg/></html>', new Policy(), RemovalReason::NotSvg, 'html'];
        yield 'svg of another namespace' => ['<svg xmlns="urn:x"/>', new Policy(), RemovalReason::NotSvg, 'svg'];
        yield 'too many elements' => ['<svg><g/><foo/></svg>', new Policy(maxElements: 2), RemovalReason::TooManyElements, '3 elements'];
        yield 'too deep' => ['<svg><g><g/></g></svg>', new Policy(maxDepth: 2), RemovalReason::TooDeep, 'more than 2 levels'];
        yield 'too many attributes' => ['<svg><g a="1" b=\'2\' c = "3"/></svg>', new Policy(maxAttributes: 2), RemovalReason::TooManyAttributes, 'more than 2 attributes'];
        yield 'too many attributes on the root' => ['<svg xmlns="http://www.w3.org/2000/svg" a="1"/>', new Policy(maxAttributes: 1), RemovalReason::TooManyAttributes, 'more than 1 attributes'];
        yield 'too many attributes, malformed besides' => ['<svg><g a="1" b="2" c="3"', new Policy(maxAttributes: 2), RemovalReason::TooManyAttributes, 'more than 2 attributes'];
    }

    /**
     * The attributes of each start tag are counted before the image is parsed, in one pass over its text: an `=`
     * between quotes, in a comment, in a section of character data, in a processing instruction, in a declaration or
     * in text is not an attribute.
     */
    public function testOnlyTheAttributesOfStartTagsAreCounted(): void
    {
        $sanitizer = new SvgSanitizer(new Policy(maxAttributes: 2)->withAttributes('a', 'b'));

        self::assertSame(self::OPEN.'<g a="x=y=z" b="&gt;c=d"/></svg>', $sanitizer->sanitize('<svg><g a="x=y=z" b=\'>c=d\'/></svg>')->svg);
        self::assertSame(self::OPEN.'<g a="1" b="2">x=y=z</g><g a="1" b="2"/></svg>', $sanitizer->sanitize('<svg><g a="1" b="2">x=y=z</g ><g a="1" b="2"/></svg>')->svg);
        self::assertSame(self::OPEN.'<g>a=b c=d</g></svg>', $sanitizer->sanitize('<!DOCTYPE svg PUBLIC "a=b" "c=d"><?xml-model a="1" b="2" c="3"?><svg><!-- a="1" b="2" c="3" --><g><![CDATA[a=b c=d]]></g></svg>')->svg);
        self::assertTrue($sanitizer->sanitize('<svg><g a="1" b="2" c="3"/></svg>')->isRefused());
        self::assertSame(self::OPEN.'<g/></svg>', $sanitizer->sanitize('<svg><!-- > <g a="1" b="2" c="3"/> --><g/></svg>')->svg, 'a comment is skipped whole');
        self::assertSame(self::OPEN.'<g> &gt; &lt;g a="1" b="2" c="3"/&gt; </g></svg>', $sanitizer->sanitize('<svg><g><![CDATA[ > <g a="1" b="2" c="3"/> ]]></g></svg>')->svg, 'so is a section of character data');
        self::assertSame(self::OPEN.'<g/></svg>', $sanitizer->sanitize('<svg><?x > <g a="1" b="2" c="3"/> ?><g/></svg>')->svg, 'and a processing instruction');
        self::assertSame([RemovalReason::TooManyAttributes], $this->reasons($sanitizer->sanitize('<svg><!-x > <g a="1" b="2" c="3"/></svg>')), 'what is not a comment is a declaration');
        self::assertSame([RemovalReason::TooManyAttributes], $this->reasons($sanitizer->sanitize('<svg><![CDATAx > <g a="1" b="2" c="3"/></svg>')), 'what is not a section is a declaration');
        self::assertSame([RemovalReason::Malformed], $this->reasons($sanitizer->sanitize('<svg><!x a=1 b=2 c=3></svg>')), 'a declaration is skipped whole');
        self::assertSame([RemovalReason::TooManyAttributes], $this->reasons($sanitizer->sanitize('<svg><= b="2" c="3"/></svg>')), 'the first character after < is read');
        self::assertSame([RemovalReason::TooManyAttributes], $this->reasons($sanitizer->sanitize('<svg><g a="1" <g b="1" c="2" d="3"/></svg>')), 'a < ends a start tag');
        self::assertSame([RemovalReason::Malformed], $this->reasons($sanitizer->sanitize('<svg><g a="1" b="2" <g c="3"/></svg>')), 'and the next one is counted alone');
        self::assertSame([RemovalReason::Malformed], array_map(static fn (Removal $removal): RemovalReason => $removal->reason, $sanitizer->sanitize('<svg><g a="1 b="2" c="3"/></svg>')->removals), 'a quote left open hides the rest');
        self::assertSame([RemovalReason::Malformed], array_map(static fn (Removal $removal): RemovalReason => $removal->reason, $sanitizer->sanitize('<svg><!-- a="1" b="2" c="3"')->removals), 'a comment left open hides the rest');
        self::assertSame([RemovalReason::Malformed], array_map(static fn (Removal $removal): RemovalReason => $removal->reason, $sanitizer->sanitize('<svg><![CDATA[ a="1" b="2" c="3"')->removals));
        self::assertSame([RemovalReason::Malformed], array_map(static fn (Removal $removal): RemovalReason => $removal->reason, $sanitizer->sanitize('<svg><? a="1" b="2" c="3"')->removals));
        self::assertSame([RemovalReason::Malformed], array_map(static fn (Removal $removal): RemovalReason => $removal->reason, $sanitizer->sanitize('<svg></g a="1" b="2" c="3"')->removals));
        self::assertSame([RemovalReason::Malformed], array_map(static fn (Removal $removal): RemovalReason => $removal->reason, $sanitizer->sanitize('<svg><g a="1"')->removals));
    }

    /**
     * libxml checks each attribute of an element against the ones before it: an element with tens of thousands of
     * attributes, in a few hundred kilobytes, would hold the parser for seconds. It is refused before parsing.
     */
    public function testAnElementWithThousandsOfAttributesIsRefusedBeforeParsing(): void
    {
        $attributes = '';

        for ($index = 0; $index < 20_000; ++$index) {
            $attributes .= ' z'.$index.'=""';
        }

        $started = hrtime(true);
        $sanitized = new SvgSanitizer()->sanitize('<svg xmlns="http://www.w3.org/2000/svg"><g'.$attributes.'/></svg>');

        self::assertLessThan(1_000_000_000, hrtime(true) - $started);
        self::assertSame([[RemovalReason::TooManyAttributes, 'more than 256 attributes', 1]], $this->removals($sanitized));
        self::assertSame(256, new Policy()->maxAttributes);
    }

    #[DataProvider('refusals')]
    public function testAnImageIsRefusedWhole(string $svg, Policy $policy, RemovalReason $reason, string $subject): void
    {
        $sanitized = new SvgSanitizer($policy)->sanitize($svg);

        self::assertSame('', $sanitized->svg);
        self::assertTrue($sanitized->isRefused());
        self::assertFalse($sanitized->isClean());
        self::assertSame([[$reason, $subject, 1]], $this->removals($sanitized));
    }

    public function testTheLimitsAreInclusive(): void
    {
        self::assertSame(self::OPEN.'<g/></svg>', new SvgSanitizer(new Policy(maxBytes: 15, maxDepth: 2, maxElements: 2))->sanitize('<svg><g/></svg>')->svg);
        self::assertSame(self::OPEN.'<g width="1" height="2"/></svg>', new SvgSanitizer(new Policy(maxAttributes: 2))->sanitize('<svg><g width="1" height="2"/></svg>')->svg);
        self::assertSame(self::EMPTY, new SvgSanitizer(new Policy(maxDepth: 1))->sanitize('<svg><script><g><g/></g></script></svg>')->svg);
    }

    public function testAnElementInsideStyleIsLeftOut(): void
    {
        $svg = '<svg id="m"><style>a { fill: <g>x</g>red }</style></svg>';

        $scope = 'svg-drawing-'.substr(hash('sha256', $svg), 0, 16);

        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg" id="'.$scope.'"><style>#'.$scope.' a {'."\n".'  fill: red;'."\n".'}'."\n".'</style></svg>', new SvgSanitizer(new Policy()->withElements('style'))->sanitize($svg)->svg);
    }

    /**
     * An image of a few megabytes can hold hundreds of thousands of removed attributes: the first hundred removals
     * are listed, and the others only counted, those of its CSS with them.
     */
    /**
     * An image written inline shares the page: a `<style>` element would style the whole page, and an `id` would
     * name a global variable of its scripts. The default policy leaves `<style>` out and prefixes every `id`, and
     * every reference to one, with `svg-`, which no script names without quotes.
     */
    public function testTheDefaultPolicyIsSafeInline(): void
    {
        $sanitized = new SvgSanitizer()->sanitize('<svg><style>form, nav { display: none } body::before { content: "Call us" }</style><rect id="appConfig"/></svg>');

        self::assertSame(self::OPEN.'<rect id="svg-appConfig"/></svg>', $sanitized->svg);
        self::assertSame([[RemovalReason::Element, 'style', 1]], $this->removals($sanitized));
        self::assertNotContains('style', Policy::DEFAULT_ELEMENTS);
        self::assertSame('svg-', new Policy()->idPrefix);
    }

    public function testIdsAndTheReferencesToThemArePrefixed(): void
    {
        $svg = '<svg id="r" xmlns:xlink="http://www.w3.org/1999/xlink"><linearGradient id="g"/><use href=" #g "/><use xlink:href="#g"/><a href="#top">t</a>'
            .'<rect fill="url(#g)" stroke="url( \'#g\' ) blue" mask="URL(&quot;#m&quot;)" style="fill: url(#g)"/><style>rect { fill: url(#g) }</style></svg>';
        $policy = new Policy()->withElements('style');
        $scope = 'svg-drawing-'.substr(hash('sha256', $svg), 0, 16);

        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$scope.'"><linearGradient id="svg-g"/><use href="#svg-g"/><use xmlns:xlink="http://www.w3.org/1999/xlink" xlink:href="#svg-g"/><a href="#svg-top">t</a>'
            .'<rect fill="url(#svg-g)" stroke="url( \'#svg-g\' ) blue" mask="URL(&quot;#svg-m&quot;)" style="fill: url(&quot;#svg-g&quot;)"/><style>#'.$scope.' rect {'."\n".'  fill: url("#svg-g");'."\n".'}'."\n".'</style></svg>',
            new SvgSanitizer($policy)->sanitize($svg)->svg,
        );
        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg"><g id="logo-a"/><use href="#logo-a"/><rect fill="url(#logo-a)"/></svg>',
            new SvgSanitizer($policy->withIdPrefix('logo-'))->sanitize('<svg><g id="a"/><use href="#a"/><rect fill="url(#a)"/></svg>')->svg,
        );
        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg"><g id="a"/><use href="#a"/><rect fill="url(#a)" style="fill: url(&quot;#a&quot;)"/></svg>',
            new SvgSanitizer($policy->withIdPrefix(null))->sanitize('<svg><g id="a"/><use href="#a"/><rect fill="url(#a)" style="fill: url(#a)"/></svg>')->svg,
        );
        self::assertSame(self::OPEN.'<text>url(#a)</text></svg>', new SvgSanitizer()->sanitize('<svg><text>url(#a)</text></svg>')->svg, 'text is not CSS');
        self::assertSame(self::OPEN.'<rect style="fill: url(&quot;#&quot;)"/></svg>', new SvgSanitizer()->sanitize('<svg><rect style="fill: url(#)"/></svg>')->svg, 'an empty fragment names no id');
    }

    public function testItListsTheFirstHundredRemovalsAndCountsTheOthers(): void
    {
        $sanitizer = new SvgSanitizer(new Policy()->withElements('style'));

        $exact = $sanitizer->sanitize('<svg>'.str_repeat('<g data-x=""/>', 100).'</svg>');
        self::assertCount(100, $exact->removals);
        self::assertSame(0, $exact->omittedRemovals);

        $many = $sanitizer->sanitize("<svg>\n".str_repeat('<g data-x=""/>', 150).'</svg>');
        self::assertCount(Sanitized::MAX_REMOVALS, $many->removals);
        self::assertSame(50, $many->omittedRemovals);
        self::assertSame([RemovalReason::Attribute, 'data-x', 2], $this->removals($many)[99]);
        self::assertFalse($many->isClean());
        self::assertFalse($many->isRefused());

        $styled = $sanitizer->sanitize('<svg>'.str_repeat('<g data-x=""/>', 60).'<style>'.str_repeat('a{x:1}', 150).'</style></svg>');
        self::assertCount(100, $styled->removals);
        self::assertSame([RemovalReason::Style, 'style: x', 1], $this->removals($styled)[99]);
        self::assertSame(110, $styled->omittedRemovals, 'sixty of the CSS removals the SVG does not list, and fifty the CSS did not');
        self::assertSame(200, $sanitizer->sanitize('<svg>'.str_repeat('<g data-x=""/>', 150).'<style>'.str_repeat('a{x:1}', 150).'</style></svg>')->omittedRemovals, 'the counts add up');

        self::assertSame(0, new Sanitized('')->omittedRemovals);
        self::assertSame(100, Sanitized::MAX_REMOVALS);
    }

    public function testASubjectIsCut(): void
    {
        $removal = new Removal(RemovalReason::Link, 'a'.str_repeat('é', 80), 3);

        self::assertSame('a'.str_repeat('é', 79).'…', $removal->subject);
        self::assertSame(3, $removal->line);
        self::assertSame(str_repeat('é', 80), new Removal(RemovalReason::Link, str_repeat('é', 80))->subject);
        self::assertSame(1, new Removal(RemovalReason::Link, 'x')->line);
    }

    /**
     * @return list<RemovalReason>
     */
    private function reasons(Sanitized $sanitized): array
    {
        return array_map(static fn (Removal $removal): RemovalReason => $removal->reason, $sanitized->removals);
    }

    /**
     * @return list<array{RemovalReason, string, int}>
     */
    private function removals(Sanitized $sanitized): array
    {
        return array_map(static fn (Removal $removal): array => [$removal->reason, $removal->subject, $removal->line], $sanitized->removals);
    }
}
