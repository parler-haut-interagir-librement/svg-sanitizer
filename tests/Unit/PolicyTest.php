<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Tests\Unit;

use Phil\CssSanitizer\Policy as CssPolicy;
use Phil\SvgSanitizer\Exception\InvalidPolicy;
use Phil\SvgSanitizer\Exception\SvgSanitizerException;
use Phil\SvgSanitizer\Policy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Policy::class)]
#[CoversClass(InvalidPolicy::class)]
final class PolicyTest extends TestCase
{
    public function testItHasDefaults(): void
    {
        $policy = new Policy();

        self::assertSame(array_fill_keys(Policy::DEFAULT_ELEMENTS, true), $policy->elements);
        self::assertSame(array_fill_keys(Policy::DEFAULT_ATTRIBUTES, true), $policy->attributes);
        self::assertSame(['http' => true, 'https' => true, 'mailto' => true], $policy->linkSchemes);
        self::assertTrue($policy->dataImages);
        self::assertSame(4_194_304, $policy->maxBytes);
        self::assertSame(64, $policy->maxDepth);
        self::assertSame(50_000, $policy->maxElements);
        self::assertSame(256, $policy->maxAttributes);
        self::assertArrayHasKey('fill', $policy->css->properties);
        self::assertArrayHasKey('stroke-dasharray', $policy->css->properties);
        self::assertArrayHasKey('color', $policy->css->properties);
        self::assertArrayHasKey('fill', $policy->attributes);
        self::assertArrayNotHasKey('animate', $policy->elements);
        self::assertArrayNotHasKey('attributeName', $policy->attributes);
        self::assertTrue($policy->allowsElement('linearGradient'));
        self::assertFalse($policy->allowsElement('lineargradient'));
        self::assertTrue($policy->allowsAttribute('viewBox'));
        self::assertFalse($policy->allowsAttribute('viewbox'));
        self::assertTrue($policy->allowsLinkScheme('HTTPS'));
        self::assertFalse($policy->allowsLinkScheme('ftp'));
    }

    public function testItTakesAPolicyOfCss(): void
    {
        $css = new CssPolicy();

        self::assertSame($css, new Policy($css)->css);
        self::assertSame($css, new Policy()->withCss($css)->css);
    }

    public function testItAddsAndRemovesNames(): void
    {
        $policy = new Policy();
        $changed = $policy
            ->withElements('metadata')->withoutElements('text', 'tspan')
            ->withAttributes('data-name', 'xlink:role')->withoutAttributes('class')
            ->withLinkSchemes('FTP', 'tel')
            ->withDataImages(false);

        self::assertTrue($changed->allowsElement('metadata'));
        self::assertFalse($changed->allowsElement('text'));
        self::assertFalse($changed->allowsElement('tspan'));
        self::assertTrue($changed->allowsElement('g'));
        self::assertTrue($changed->allowsAttribute('data-name'));
        self::assertTrue($changed->allowsAttribute('xlink:role'));
        self::assertFalse($changed->allowsAttribute('class'));
        self::assertTrue($changed->allowsAttribute('id'));
        self::assertSame(['ftp' => true, 'tel' => true], $changed->linkSchemes);
        self::assertFalse($changed->dataImages);
        self::assertSame([], $policy->withLinkSchemes()->linkSchemes);
        self::assertTrue(new Policy()->withDataImages(false)->withDataImages(true)->dataImages);
        self::assertTrue($policy->allowsElement('text'));
        self::assertFalse($policy->allowsElement('metadata'));
    }

    public function testItAllowsAnimations(): void
    {
        $policy = new Policy()->withAnimations();

        foreach ([...Policy::ANIMATION_ELEMENTS, 'svg'] as $element) {
            self::assertTrue($policy->allowsElement($element), $element);
        }

        foreach ([...Policy::ANIMATION_ATTRIBUTES, 'id'] as $attribute) {
            self::assertTrue($policy->allowsAttribute($attribute), $attribute);
        }
    }

    public function testItChangesItsLimits(): void
    {
        $policy = new Policy()->withMaxBytes(1)->withMaxDepth(2)->withMaxElements(3)->withMaxAttributes(4);

        self::assertSame([1, 2, 3, 4], [$policy->maxBytes, $policy->maxDepth, $policy->maxElements, $policy->maxAttributes]);
        self::assertSame(1, new Policy(maxAttributes: 1)->maxAttributes);
        self::assertSame([1, 2, 3], [new Policy(maxBytes: 1, maxDepth: 2, maxElements: 3)->maxBytes, new Policy(maxDepth: 2)->maxDepth, new Policy(maxElements: 3)->maxElements]);
    }

    public function testItChangesTheIdPrefix(): void
    {
        $policy = new Policy();

        self::assertSame(Policy::DEFAULT_ID_PREFIX, $policy->idPrefix);
        self::assertSame('svg-', Policy::DEFAULT_ID_PREFIX);
        self::assertSame('logo-', $policy->withIdPrefix('logo-')->idPrefix);
        self::assertNull($policy->withIdPrefix(null)->idPrefix);
        self::assertNull(new Policy(idPrefix: null)->idPrefix);
        self::assertSame('svg-', $policy->idPrefix, 'the policy itself is left as it was');
    }

    public function testItAllowsStyleElements(): void
    {
        $policy = new Policy();
        $styled = $policy->withStyleElements(true);

        self::assertFalse($policy->allowsStyleElements());
        self::assertFalse($policy->allowsElement('style'));
        self::assertTrue($styled->allowsStyleElements());
        self::assertTrue($styled->allowsElement('style'));
        self::assertTrue($styled->allowsElement('rect'), 'the other elements stay');
        self::assertFalse($styled->withStyleElements(false)->allowsStyleElements());
        self::assertTrue($styled->withStyleElements(false)->allowsElement('rect'));
        self::assertTrue(new Policy(styleElements: true)->allowsStyleElements());
        self::assertSame(array_fill_keys([...Policy::DEFAULT_ELEMENTS, 'style'], true), new Policy(styleElements: true)->elements);
        self::assertFalse(new Policy(styleElements: false)->allowsStyleElements());
        self::assertTrue($policy->withElements('style')->allowsStyleElements(), 'allowing the element is the same');
        self::assertFalse($policy->allowsStyleElements(), 'the policy itself is left as it was');
    }

    /**
     * @return iterable<string, array{callable(): mixed, non-empty-string}>
     */
    public static function refusals(): iterable
    {
        yield 'script' => [static fn (): Policy => new Policy()->withElements('g', 'script'), 'The element "script" can run code, embed another document or load a resource; it is never allowed.'];
        yield 'foreignObject' => [static fn (): Policy => new Policy()->withElements('foreignObject'), 'The element "foreignObject"'];
        yield 'SCRIPT' => [static fn (): Policy => new Policy()->withElements('SCRIPT'), 'The element "SCRIPT"'];
        yield 'onclick' => [static fn (): Policy => new Policy()->withAttributes('id', 'onclick'), 'The attribute "onclick" can run code or change where references point; it is never allowed.'];
        yield 'OnLoad' => [static fn (): Policy => new Policy()->withAttributes('OnLoad'), 'The attribute "OnLoad"'];
        yield 'xml:base' => [static fn (): Policy => new Policy()->withAttributes('XML:base'), 'The attribute "XML:base"'];
        yield 'javascript' => [static fn (): Policy => new Policy()->withLinkSchemes('https', 'JavaScript'), 'The scheme "JavaScript:" can run code or hold a document; links may never use it.'];
        yield 'data' => [static fn (): Policy => new Policy()->withLinkSchemes('data'), 'The scheme "data:"'];
        yield 'bytes' => [static fn (): Policy => new Policy(maxBytes: 0), 'The maximum size must be at least 1; got 0.'];
        yield 'depth' => [static fn (): Policy => new Policy(maxDepth: 0), 'The maximum depth must be at least 1; got 0.'];
        yield 'elements' => [static fn (): Policy => new Policy(maxElements: -1), 'The maximum number of elements must be at least 1; got -1.'];
        yield 'withMaxBytes' => [static fn (): Policy => new Policy()->withMaxBytes(0), 'The maximum size must be at least 1; got 0.'];
        yield 'withMaxDepth' => [static fn (): Policy => new Policy()->withMaxDepth(0), 'The maximum depth must be at least 1; got 0.'];
        yield 'withMaxElements' => [static fn (): Policy => new Policy()->withMaxElements(0), 'The maximum number of elements must be at least 1; got 0.'];
        yield 'attributes' => [static fn (): Policy => new Policy(maxAttributes: 0), 'The maximum number of attributes must be at least 1; got 0.'];
        yield 'withMaxAttributes' => [static fn (): Policy => new Policy()->withMaxAttributes(0), 'The maximum number of attributes must be at least 1; got 0.'];
    }

    /**
     * @param callable(): mixed $build
     * @param non-empty-string  $message
     */
    #[DataProvider('refusals')]
    public function testItRefusesWhatIsNeverAllowed(callable $build, string $message): void
    {
        try {
            $build();
            self::fail('No exception.');
        } catch (InvalidPolicy $invalidPolicy) {
            self::assertInstanceOf(SvgSanitizerException::class, $invalidPolicy);
            self::assertStringStartsWith($message, $invalidPolicy->getMessage());
        }
    }

    public function testItKnowsWhatIsDenied(): void
    {
        self::assertTrue(Policy::isDeniedElement('ForeignObject'));
        self::assertFalse(Policy::isDeniedElement('g'));
        self::assertTrue(Policy::isDeniedAttribute('ONclick'));
        self::assertTrue(Policy::isDeniedAttribute('xml:BASE'));
        self::assertFalse(Policy::isDeniedAttribute('xml:lang'));
        self::assertFalse(Policy::isDeniedAttribute('opacity'));
        self::assertFalse(Policy::isDeniedAttribute('xon'));
        self::assertSame(1, new Policy(maxBytes: 1)->withMaxBytes(1)->maxBytes);
    }
}
