<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Tests\Unit;

use Phil\CssSanitizer\Url\CallbackRewriter;
use Phil\SvgSanitizer\Internal\Sanitization;
use Phil\SvgSanitizer\Internal\ScopedStyleSheet;
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
 * A `<style>` element kept by the policy: every rule placed under the image's own root, which nothing outside the
 * image can match, so that no rule reaches the rest of the page.
 */
#[CoversClass(SvgSanitizer::class)]
#[CoversClass(Sanitization::class)]
#[CoversClass(ScopedStyleSheet::class)]
#[UsesClass(Policy::class)]
#[UsesClass(Sanitized::class)]
#[UsesClass(Removal::class)]
#[UsesClass(RemovalReason::class)]
final class StyleElementTest extends TestCase
{
    private const string OPEN = '<svg xmlns="http://www.w3.org/2000/svg" id="svg-m">';

    public function testTheRulesOfADiagramReachItsOwnElements(): void
    {
        $svg = '<svg id="m"><style>#m{font-family:arial;fill:#333}#m .node rect{fill:#ECECFF}#m>g{stroke:red}.label{color:#333}</style><g class="node"><rect/></g></svg>';

        $sanitized = $this->sanitize($svg);

        $expected = self::OPEN.'<style>#svg-m {'."\n".'  font-family: arial;'."\n".'  fill: #333;'."\n".'}'."\n\n"
            .'#svg-m .node rect {'."\n".'  fill: #ECECFF;'."\n".'}'."\n\n"
            .'#svg-m&gt;g {'."\n".'  stroke: red;'."\n".'}'."\n\n"
            .'#svg-m .label {'."\n".'  color: #333;'."\n".'}'."\n"
            .'</style><g class="node"><rect/></g></svg>';
        self::assertSame($this->scoped($svg, $expected), $sanitized->svg);
        self::assertTrue($sanitized->isClean());
    }

    public function testARootWithoutIdIsGivenOne(): void
    {
        $svg = '<svg><style>rect{fill:red}</style><style>g{fill:blue}</style><rect/></svg>';
        $id = $this->scope($svg);

        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$id.'"><style>#'.$id.' rect {'."\n".'  fill: red;'."\n".'}'."\n".'</style>'
            .'<style>#'.$id.' g {'."\n".'  fill: blue;'."\n".'}'."\n".'</style><rect/></svg>',
            $this->sanitize($svg)->svg,
        );
        $bare = '<svg><style>a{fill:red}</style></svg>';
        $bareId = $this->scope($bare, '');
        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$bareId.'"><style>#'.$bareId.' a {'."\n".'  fill: red;'."\n".'}'."\n".'</style></svg>',
            new SvgSanitizer(new Policy(idPrefix: null, styleElements: true))->sanitize($bare)->svg,
            'without prefix, the generated id has none either',
        );
    }

    public function testARootIsGivenAnIdOnlyWhenAStyleIsKept(): void
    {
        $sanitized = $this->sanitize('<svg><style>rect { cursor: pointer }</style><style/><rect/></svg>');

        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>', $sanitized->svg);
        self::assertSame([[RemovalReason::Style, 'style: cursor', 1]], $this->removals($sanitized));
    }

    public function testTheIdsTheRulesNameArePrefixed(): void
    {
        $svg = '<svg id="m"><style>#a, :not(#b) { fill: url(#g) }</style><g id="a"/></svg>';
        self::assertSame(
            $this->scoped($svg, self::OPEN.'<style>#svg-m #svg-a, #svg-m :not(#svg-b) {'."\n".'  fill: url("#svg-g");'."\n".'}'."\n".'</style><g id="svg-a"/></svg>'),
            $this->sanitize($svg)->svg,
        );
        $bare = '<svg id="m"><style>#m .a { fill: red }</style></svg>';
        $scope = $this->scope($bare, '');
        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$scope.'"><style>#'.$scope.' .a {'."\n".'  fill: red;'."\n".'}'."\n".'</style></svg>',
            new SvgSanitizer(new Policy(idPrefix: null, styleElements: true))->sanitize($bare)->svg,
        );
    }

    public function testNestedRulesAreNeitherScopedNorCut(): void
    {
        $svg = '<svg id="m"><style>.a { .b, #c { fill: red } } [lang="fr"] { fill: blue }</style></svg>';
        self::assertSame(
            $this->scoped($svg, self::OPEN.'<style>#svg-m .a {'."\n".'  .b, #svg-c {'."\n".'    fill: red;'."\n".'  }'."\n".'}'."\n\n"
            .'#svg-m [lang="fr"] {'."\n".'  fill: blue;'."\n".'}'."\n".'</style></svg>'),
            $this->sanitize($svg)->svg,
        );
    }

    public function testTheRootScopesEveryStyleWhateverTheirOwnIds(): void
    {
        $svg = '<svg id="m"><g><style id="s">a { fill: red }</style></g></svg>';
        self::assertSame(
            $this->scoped($svg, self::OPEN.'<g><style id="svg-s">#svg-m a {'."\n".'  fill: red;'."\n".'}'."\n".'</style></g></svg>'),
            $this->sanitize($svg)->svg,
        );
    }

    public function testAPrefixThatIsNotAnIdentifierIsEscaped(): void
    {
        $svg = '<svg id="m"><style>#a { fill: red }</style></svg>';
        $hash = substr($this->scope($svg, ''), \strlen('drawing-'));
        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="1-drawing-'.$hash.'"><style>#\31 -drawing-'.$hash.' #\31 -a {'."\n".'  fill: red;'."\n".'}'."\n".'</style></svg>',
            new SvgSanitizer(new Policy(idPrefix: '1-', styleElements: true))->sanitize($svg)->svg,
        );
    }

    public function testARootIdThatIsNotAnIdentifierIsReplaced(): void
    {
        $svg = '<svg id="x, body"><style>p { fill: red } #x\2c \ body { fill: blue }</style></svg>';
        $scope = $this->scope($svg);

        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$scope.'"><style>#'.$scope.' p {'."\n".'  fill: red;'."\n".'}'."\n\n"
            .'#'.$scope.' {'."\n".'  fill: blue;'."\n".'}'."\n".'</style></svg>',
            $this->sanitize($svg)->svg,
        );
    }

    /**
     * @return iterable<string, array{string, string, list<array{RemovalReason, string, int}>}>
     */
    public static function hostile(): iterable
    {
        yield 'the page itself' => [
            'body { display: none } html, :root { fill: red } \62 ody { fill: red }',
            '#svg-m body {'."\n".'  display: none;'."\n".'}'."\n\n".'#svg-m html, #svg-m :root {'."\n".'  fill: red;'."\n".'}'."\n\n".'#svg-m body {'."\n".'  fill: red;'."\n".'}'."\n",
            [],
        ];
        yield 'imports, pages and fonts' => [
            '@import url(x.css); @page { margin: 0 } @font-face { font-family: Inter; src: local(Arial) } rect { fill: red }',
            '#svg-m rect {'."\n".'  fill: red;'."\n".'}'."\n",
            [[RemovalReason::Style, 'style: @import', 1], [RemovalReason::Style, 'style: @page', 1], [RemovalReason::Style, 'style: @font-face', 1]],
        ];
        yield 'media' => [
            '@media print { body { display: none } }',
            '@media print {'."\n".'  #svg-m body {'."\n".'    display: none;'."\n".'  }'."\n".'}'."\n",
            [],
        ];
        yield 'the siblings of the root' => [
            '#m ~ p, #m + p { display: none } #m { & + p { display: none } } #m .a { & + p { fill: red } }',
            '#svg-m #svg-m ~ p, #svg-m #svg-m + p {'."\n".'  display: none;'."\n".'}'."\n\n"
            .'#svg-m #svg-m {'."\n".'  &amp; + p {'."\n".'    display: none;'."\n".'  }'."\n".'}'."\n\n"
            .'#svg-m .a {'."\n".'  &amp; + p {'."\n".'    fill: red;'."\n".'  }'."\n".'}'."\n",
            [],
        ];
        yield 'the nesting selector' => [
            '& { display: none } .a { :not(&) { display: none } fill: red }',
            '#svg-m .a {'."\n".'  fill: red;'."\n".'}'."\n",
            [[RemovalReason::Style, 'style: &', 1], [RemovalReason::Style, 'style: :not(&)', 1]],
        ];
        yield 'escapes' => [
            '#m\,body { fill: red } a\{\}body { fill: red } #\6d  .a { fill: red }',
            '#svg-m #svg-m\,body {'."\n".'  fill: red;'."\n".'}'."\n\n".'#svg-m a\{\}body {'."\n".'  fill: red;'."\n".'}'."\n\n".'#svg-m .a {'."\n".'  fill: red;'."\n".'}'."\n",
            [],
        ];
        yield 'external addresses' => [
            '.a { fill: url(http://x.test/p) } .b { fill: url(#g) }',
            '#svg-m .b {'."\n".'  fill: url("#svg-g");'."\n".'}'."\n",
            [[RemovalReason::ExternalReference, 'style: http://x.test/p', 1]],
        ];
    }

    /**
     * @param list<array{RemovalReason, string, int}> $removals
     */
    #[DataProvider('hostile')]
    public function testNoRuleReachesThePage(string $css, string $expected, array $removals): void
    {
        $svg = '<svg id="m"><style><![CDATA['.$css.']]></style></svg>';
        $sanitized = $this->sanitize($svg);

        self::assertSame($this->scoped($svg, self::OPEN.'<style>'.$expected.'</style></svg>'), $sanitized->svg);
        self::assertSame($removals, $this->removals($sanitized));
    }

    public function testAStyleCannotCloseItself(): void
    {
        $cdataSvg = '<svg id="m"><style><![CDATA[a{fill:red}</style><script>alert(1)</script>]]></style></svg>';
        $elementSvg = '<svg id="m"><style>a{fill:red}</style><script>alert(1)</script></svg>';
        $stringSvg = '<svg id="m"><style><![CDATA[a::before{content:"</style><script>alert(1)</script>"}]]></style></svg>';
        $cdata = $this->sanitize($cdataSvg);
        $element = $this->sanitize($elementSvg);
        $string = $this->sanitize($stringSvg);

        self::assertSame($this->scoped($cdataSvg, self::OPEN.'<style>#svg-m a {'."\n".'  fill: red;'."\n".'}'."\n".'</style></svg>'), $cdata->svg);
        self::assertSame(RemovalReason::Style, $cdata->removals[0]->reason);
        self::assertSame($this->scoped($elementSvg, self::OPEN.'<style>#svg-m a {'."\n".'  fill: red;'."\n".'}'."\n".'</style></svg>'), $element->svg);
        self::assertSame([[RemovalReason::DeniedElement, 'script', 1]], $this->removals($element));
        self::assertSame($this->scoped($stringSvg, self::OPEN.'<style>#svg-m a::before {'."\n".'  content: "\3c /style&gt;\3c script&gt;alert(1)\3c /script&gt;";'."\n".'}'."\n".'</style></svg>'), $string->svg);
        self::assertStringNotContainsString('<script', $string->svg);
    }

    public function testTheApplicationsRewriterDoesNotOpenStyleElements(): void
    {
        $policy = new Policy(styleElements: true);
        $policy = $policy->withCss($policy->css->withUrlRewriter(new CallbackRewriter(static fn (string $url): string => $url)));

        $svg = '<svg id="m"><style>.a { fill: url(https://x.test/p) }</style><rect style="fill: url(https://x.test/p)"/></svg>';
        $sanitized = new SvgSanitizer($policy)->sanitize($svg);

        self::assertSame($this->scoped($svg, self::OPEN).'<rect style="fill: url(&quot;https://x.test/p&quot;)"/></svg>', $sanitized->svg);
        self::assertSame([[RemovalReason::ExternalReference, 'style: https://x.test/p', 1]], $this->removals($sanitized));
    }

    public function testTheDefaultPolicyStillLeavesStyleOut(): void
    {
        $sanitized = new SvgSanitizer()->sanitize('<svg id="m"><style>rect { fill: red }</style></svg>');

        self::assertSame('<svg xmlns="http://www.w3.org/2000/svg" id="svg-m"/>', $sanitized->svg);
        self::assertSame([[RemovalReason::Element, 'style', 1]], $this->removals($sanitized));
    }

    public function testTwoDrawingsWithTheSameRootIdHaveDistinctScopes(): void
    {
        $first = '<svg id="diagram"><style>#diagram .node { fill: red }</style><g class="node"/></svg>';
        $second = '<svg id="diagram"><style>#diagram .node { fill: blue } body { display: none }</style><g class="node"/></svg>';

        $one = $this->sanitize($first)->svg;
        $two = $this->sanitize($second)->svg;
        $firstScope = $this->scope($first);
        $secondScope = $this->scope($second);

        self::assertNotSame($firstScope, $secondScope);
        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$firstScope.'"><style>#'.$firstScope.' .node {'."\n".'  fill: red;'."\n".'}'."\n".'</style><g class="node"/></svg>',
            $one,
        );
        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$secondScope.'"><style>#'.$secondScope.' .node {'."\n".'  fill: blue;'."\n".'}'."\n\n"
            .'#'.$secondScope.' body {'."\n".'  display: none;'."\n".'}'."\n".'</style><g class="node"/></svg>',
            $two,
        );
        self::assertStringNotContainsString('svg-diagram', $one.$two);
        self::assertStringNotContainsString($firstScope, $two);
    }

    public function testAnInnerIdCannotBorrowTheScopeOfAnotherDrawing(): void
    {
        $first = '<svg id="diagram"><style>.node { fill: red }</style></svg>';
        $borrowed = substr($this->scope($first), \strlen('svg-'));
        $second = '<svg id="diagram"><style>#'.$borrowed.' .node { fill: blue }</style><g id="'.$borrowed.'"><g class="node"/></g></svg>';

        $two = $this->sanitize($second)->svg;

        self::assertStringContainsString('#'.$this->scope($second).' #'.$this->scope($first).' .node {', $two, 'the rule stays under its own drawing');
    }

    public function testEveryReferenceToTheRootPointsToItsScope(): void
    {
        $svg = '<svg id="m"><style>.a { fill: url(#m) } #m:hover { fill: red }</style><use href="#m"/><rect style="fill: url(#m)" fill="url(#m)"/><g id="n"/></svg>';
        $scope = $this->scope($svg);

        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$scope.'"><style>#'.$scope.' .a {'."\n".'  fill: url("#'.$scope.'");'."\n".'}'."\n\n"
            .'#'.$scope.':hover {'."\n".'  fill: red;'."\n".'}'."\n".'</style><use href="#'.$scope.'"/>'
            .'<rect style="fill: url(&quot;#'.$scope.'&quot;)" fill="url(#'.$scope.')"/><g id="svg-n"/></svg>',
            $this->sanitize($svg)->svg,
        );

        $bare = '<svg id="m"><use href="#m"/><rect fill="url(#m)"/></svg>';
        $bareScope = $this->scope($bare, '');
        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" id="'.$bareScope.'"><use href="#'.$bareScope.'"/><rect fill="url(#'.$bareScope.')"/></svg>',
            new SvgSanitizer(new Policy(idPrefix: null, styleElements: true))->sanitize($bare)->svg,
            'without prefix, the root is still scoped',
        );
    }

    /**
     * The id that scopes the rules of an image whose `<style>` elements are kept: the prefix, `drawing-` and sixteen
     * hexadecimal digits of the SHA-256 of the image.
     */
    private function scope(string $svg, string $prefix = 'svg-'): string
    {
        return $prefix.'drawing-'.substr(hash('sha256', $svg), 0, 16);
    }

    /**
     * An expected image with its root `id`, `svg-m`, written as the scope of the image: wherever it names the root,
     * not where it starts another id, as `#svg-m\,body`.
     */
    private function scoped(string $svg, string $expected): string
    {
        return (string) preg_replace('/svg-m(?![\\\\\w-])/', $this->scope($svg), $expected);
    }

    private function sanitize(string $svg): Sanitized
    {
        return new SvgSanitizer(new Policy(styleElements: true))->sanitize($svg);
    }

    /**
     * @return list<array{RemovalReason, string, int}>
     */
    private function removals(Sanitized $sanitized): array
    {
        return array_map(static fn (Removal $removal): array => [$removal->reason, $removal->subject, $removal->line], $sanitized->removals);
    }
}
