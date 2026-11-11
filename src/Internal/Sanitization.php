<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer\Internal;

use Dom\Attr;
use Dom\Element;
use Dom\Node;
use Dom\ProcessingInstruction;
use Dom\Text;
use Dom\XMLDocument;
use Phil\CssSanitizer\CssSanitizer;
use Phil\CssSanitizer\RemovalReason as CssReason;
use Phil\CssSanitizer\Sanitized as CssSanitized;
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\Removal;
use Phil\SvgSanitizer\RemovalReason;
use Phil\SvgSanitizer\Sanitized;
use Phil\SvgSanitizer\SvgSanitizer;

/**
 * One image sanitized: its elements copied into a new document when the policy allows them, their attributes checked
 * one by one, their CSS given to the CSS sanitizer, and every removal recorded: the first `Sanitized::MAX_REMOVALS`
 * listed, the others counted.
 *
 * @internal
 */
final class Sanitization
{
    private const string XML = 'http://www.w3.org/XML/1998/namespace';

    private const string XLINK = 'http://www.w3.org/1999/xlink';

    private const string XMLNS = 'http://www.w3.org/2000/xmlns/';

    /** The attributes whose values are lists of transforms, read as the CSS property `transform`. */
    private const array TRANSFORMS = ['gradientTransform', 'patternTransform'];

    /** The attributes an animation never targets: references, styles and event handlers. */
    private const array UNANIMATABLE = ['href', 'xlink:href', 'style', 'xml:base'];

    /** The attributes holding the values an animation sets. */
    private const array ANIMATION_VALUES = ['from', 'to', 'by', 'values'];

    /** The raster images a `data:` address may hold. */
    private const string DATA_IMAGE = '/^data:image\/(?:png|jpeg|gif|webp|avif)[;,]/i';

    /** A value made of names, numbers, colours and lists only, which CSS reads as nothing more. */
    private const string PLAIN_VALUE = '/^[A-Za-z0-9#%.,+\-\s]*$/D';

    /** The fragment that names an id in a `url()`, after its parenthesis, its spaces and its quote. */
    private const string URL_FRAGMENT = '/(url\(\s*["\']?)#([^\s"\')]+)/i';

    /** @var list<Removal> */
    private array $removals = [];

    private int $omitted = 0;

    private bool $tooDeep = false;

    private readonly XMLDocument $out;

    private readonly CssSanitizer $css;

    private readonly ScopedStyleSheet $styleSheet;

    /** The copy of the root, whose `id` scopes the rules of `<style>` elements. */
    private ?Element $root = null;

    /** The `id` of the root as the image wrote it, replaced by the scope when `<style>` elements are kept. */
    private ?string $rootId = null;

    /**
     * The `id` that scopes the rules of `<style>` elements: written from the image's content, so that two images on
     * one page that give their roots the same `id` never share a rule.
     */
    private string $scope = '';

    /**
     * @param string $source the image as it was given, from which the `id` that scopes its `<style>` elements is
     *                       written
     */
    public function __construct(
        private readonly Policy $policy,
        private readonly string $source = '',
    ) {
        $this->out = XMLDocument::createEmpty();
        $this->css = new CssSanitizer($policy->css);
        $this->styleSheet = new ScopedStyleSheet($policy->css, $this->id(...));
    }

    public function document(XMLDocument $document, Element $root): Sanitized
    {
        foreach ($document->childNodes as $node) {
            if ($node instanceof ProcessingInstruction) {
                $this->remove(RemovalReason::ProcessingInstruction, $node->target, $node);
            }
        }

        if ($this->policy->allowsStyleElements()) {
            $this->scope = $this->policy->idPrefix.'drawing-'.substr(hash('sha256', $this->source), 0, 16);
            $this->rootId = $root->getAttribute('id');
        }

        $svg = $this->element($root, 1);

        if ($this->tooDeep) {
            return new Sanitized('', [new Removal(RemovalReason::TooDeep, \sprintf('more than %d levels', $this->policy->maxDepth))]);
        }

        if (!$svg instanceof Element) {
            return new Sanitized('', $this->removals, $this->omitted);
        }

        return new Sanitized((string) $this->out->saveXml($svg), $this->removals, $this->omitted);
    }

    private function element(Element $element, int $depth): ?Element
    {
        $name = $element->localName;

        if (!\in_array($element->namespaceURI, [SvgSanitizer::SVG_NAMESPACE, null], true)) {
            return $this->remove(RemovalReason::Element, $element->nodeName, $element);
        }

        if (Policy::isDeniedElement($name)) {
            return $this->remove(RemovalReason::DeniedElement, $name, $element);
        }

        if (!$this->policy->allowsElement($name)) {
            return $this->remove(RemovalReason::Element, $name, $element);
        }

        if ($depth > $this->policy->maxDepth) {
            return $this->tooDeep();
        }

        if (!$this->animates($element)) {
            return null;
        }

        $copy = $this->out->createElementNS(SvgSanitizer::SVG_NAMESPACE, $name);
        $this->root ??= $copy;

        foreach ($element->attributes as $attribute) {
            $this->attribute($element, $attribute, $copy);
        }

        if ('style' === $name) {
            return $this->style($element, $copy);
        }

        foreach ($element->childNodes as $child) {
            $kept = match (true) {
                $child instanceof Element => $this->element($child, $depth + 1),
                $child instanceof Text => $this->out->createTextNode($child->data),
                $child instanceof ProcessingInstruction => $this->remove(RemovalReason::ProcessingInstruction, $child->target, $child),
                default => null,
            };

            if (null !== $kept) {
                $copy->append($kept);
            }
        }

        return $copy;
    }

    /**
     * Whether an element is not an animation, or one that targets neither a reference, a style nor an event handler.
     */
    private function animates(Element $element): bool
    {
        if (!\in_array($element->localName, Policy::ANIMATION_ELEMENTS, true)) {
            return true;
        }

        $target = strtolower(trim($element->getAttribute('attributeName') ?? ''));

        if (\in_array($target, self::UNANIMATABLE, true) || str_starts_with($target, 'on')) {
            $this->remove(RemovalReason::Animation, $element->localName.' '.$target, $element);

            return false;
        }

        return true;
    }

    private function attribute(Element $element, Attr $attribute, Element $copy): void
    {
        $namespace = $attribute->namespaceURI;

        if (self::XMLNS === $namespace) {
            return;
        }

        $name = match ($namespace) {
            null => str_contains($attribute->localName, ':') ? null : $attribute->localName,
            self::XML => 'xml:'.$attribute->localName,
            self::XLINK => 'xlink:'.$attribute->localName,
            default => null,
        };

        if (null === $name) {
            $this->remove(RemovalReason::Attribute, $attribute->name, $element);

            return;
        }

        if (Policy::isDeniedAttribute($name)) {
            $this->remove(str_starts_with(strtolower($name), 'on') ? RemovalReason::EventHandler : RemovalReason::Attribute, $name, $element);

            return;
        }

        if (!$this->policy->allowsAttribute($name)) {
            $this->remove(RemovalReason::Attribute, $name, $element);

            return;
        }

        $value = $this->value($element, $name, $attribute->value);

        if (null !== $value) {
            $copy->setAttributeNS($namespace, $name, $value);
        }
    }

    /**
     * The value of an allowed attribute as it is kept, or `null` when it is removed.
     */
    private function value(Element $element, string $name, string $value): ?string
    {
        return match (true) {
            'id' === $name => $this->id($value),
            'href' === $name, 'xlink:href' === $name => $this->reference($element, $value),
            'style' === $name => $this->declarations($element, $value),
            \in_array($name, Policy::PRESENTATION_ATTRIBUTES, true) => $this->presentation($element, $name, $name, $value),
            \in_array($name, self::TRANSFORMS, true) => $this->presentation($element, $name, 'transform', $value),
            \in_array($name, self::ANIMATION_VALUES, true) && \in_array($element->localName, Policy::ANIMATION_ELEMENTS, true) => $this->animationValue($element, $name, $value),
            default => $value,
        };
    }

    /**
     * A reference: a fragment of the image; a raster image in a `data:` address, on `<image>` and `<feImage>`, when
     * the policy allows them; a link of an allowed scheme, on `<a>`.
     */
    private function reference(Element $element, string $value): ?string
    {
        $address = trim($value, " \t\n\r\f");

        if ('#' === $address) {
            return $address;
        }

        if (str_starts_with($address, '#')) {
            return '#'.$this->id(substr($address, 1));
        }

        // Browsers drop the whitespace and the control characters of a scheme: `java script:` runs.
        $compact = (string) preg_replace('/[\x00-\x20]+/', '', $address);
        $scheme = 1 === preg_match('/^([a-z][a-z0-9+.\-]*):/i', $compact, $match) ? strtolower($match[1]) : null;
        $tag = $element->localName;

        if ($this->policy->dataImages && \in_array($tag, ['image', 'feImage'], true) && 1 === preg_match(self::DATA_IMAGE, $compact)) {
            return $address;
        }

        if ('a' === $tag && null !== $scheme && $this->policy->allowsLinkScheme($scheme)) {
            return $address;
        }

        return $this->remove(match (true) {
            \in_array($scheme, Policy::DENIED_SCHEMES, true) => RemovalReason::DangerousUrl,
            'a' === $tag => RemovalReason::Link,
            default => RemovalReason::ExternalReference,
        }, $address, $element);
    }

    private function declarations(Element $element, string $value): ?string
    {
        $sanitized = $this->css->sanitizeDeclarations($value);
        $this->cssRemovals($element, 'style', $sanitized);

        return '' === $sanitized->css ? null : $this->fragments($sanitized->css);
    }

    /**
     * A presentation attribute: kept as written when it holds names, numbers and colours only, or when the CSS
     * policy keeps it whole as the value of its property.
     */
    private function presentation(Element $element, string $name, string $property, string $value): ?string
    {
        if (1 === preg_match(self::PLAIN_VALUE, $value)) {
            return $value;
        }

        $sanitized = $this->css->sanitizeDeclarations($property.': '.$value);

        if ($sanitized->isClean()) {
            return $this->fragments($value);
        }

        $this->cssRemovals($element, $name, $sanitized);

        return null;
    }

    private function animationValue(Element $element, string $name, string $value): ?string
    {
        if (1 === preg_match('/[(\\\\:]/', $value)) {
            return $this->remove(RemovalReason::Animation, $name.': '.$value, $element);
        }

        return $value;
    }

    /**
     * A `<style>` element: its text given to the CSS sanitizer as a style sheet held under the root, and the element
     * left out when nothing of it is kept. The root is given the `id` that scopes it when it has none.
     */
    private function style(Element $element, Element $copy): ?Element
    {
        $css = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof Text) {
                $css .= $child->data;
            }
        }

        $root = $this->root ?? $copy;
        [$kept, $sanitized] = $this->styleSheet->sanitize($css, $this->scope);
        $this->cssRemovals($element, 'style', $sanitized);

        if ('' === $kept) {
            return null;
        }

        $root->setAttribute('id', $this->scope);
        $copy->append($this->fragments($kept));

        return $copy;
    }

    /**
     * An `id` as it is kept: the scope for the root's when `<style>` elements are kept, prefixed otherwise.
     */
    private function id(string $id): string
    {
        return $id === $this->rootId ? $this->scope : $this->policy->idPrefix.$id;
    }

    /**
     * CSS the CSS sanitizer kept, the fragments of its `url()`s written as the ids they point to. A URL written with
     * escapes is left as it is, and points to no id once they are rewritten.
     */
    private function fragments(string $css): string
    {
        return (string) preg_replace_callback(self::URL_FRAGMENT, fn (array $match): string => $match[1].'#'.$this->id($match[2]), $css);
    }

    /**
     * The removals of the CSS sanitizer, those it listed and those it only counted.
     */
    private function cssRemovals(Element $element, string $name, CssSanitized $sanitized): void
    {
        $this->omitted += $sanitized->omittedRemovals;

        foreach ($sanitized->removals as $removal) {
            $reason = match ($removal->reason) {
                CssReason::Url => RemovalReason::ExternalReference,
                CssReason::DangerousUrl => RemovalReason::DangerousUrl,
                default => RemovalReason::Style,
            };
            $this->remove($reason, $name.': '.$removal->subject, $element);
        }
    }

    /**
     * Records that the kept elements nest deeper than the policy allows: the image is refused whole.
     */
    private function tooDeep(): null
    {
        $this->tooDeep = true;

        return null;
    }

    /**
     * Lists a removal while fewer than `Sanitized::MAX_REMOVALS` are, and counts it otherwise.
     */
    private function remove(RemovalReason $reason, string $subject, Node $node): null
    {
        if (\count($this->removals) < Sanitized::MAX_REMOVALS) {
            $this->removals[] = new Removal($reason, $subject, $node->getLineNo());
        } else {
            ++$this->omitted;
        }

        return null;
    }
}
