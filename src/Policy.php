<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer;

use Phil\CssSanitizer\Policy as CssPolicy;
use Phil\SvgSanitizer\Exception\InvalidPolicy;

/**
 * What an SVG image may hold: its elements and attributes, the schemes its links may use, whether it may carry raster
 * images in `data:` addresses, the CSS policy of its `style` attributes and `<style>` elements, and the limits on its
 * size.
 *
 * `new Policy()` allows what a static drawing needs — structure, shapes, text, gradients, patterns, clipping, masks,
 * markers, filters, raster images in `data:` addresses and links to `http`, `https` and `mailto` — and nothing that
 * moves or reacts. It is safe for an image written inline in a page: it leaves out `<style>`, and prefixes every `id`
 * with `svg-`, so that none names a global variable of the page's scripts. `withStyleElements(true)` keeps `<style>`,
 * every rule of it placed under the image's root, so that none reaches the rest of the page. Each `with…()` returns a
 * new policy. Element and attribute names are compared as written, since SVG names are case-sensitive (`viewBox`,
 * `linearGradient`).
 *
 * Some things are never allowed, whatever the policy says: the elements that run code, embed another document or
 * load a resource (`script`, `foreignObject`, `iframe`, `object`, `embed`, `handler`, `listener`, `audio`, `video`,
 * `canvas`, `font-face-uri`, `cursor`, `tref`), every event handler attribute (`on…`), `xml:base`, and links to
 * `javascript:`, `vbscript:`, `data:` and their kind. Asking for them throws `InvalidPolicy`.
 */
final readonly class Policy
{
    /** The elements that run code, embed another document or load a resource; never allowed, in lower case. */
    public const array DENIED_ELEMENTS = [
        'script', 'foreignobject', 'iframe', 'object', 'embed', 'handler', 'listener', 'audio', 'video', 'canvas',
        'font-face-uri', 'font-face-src', 'cursor', 'tref',
    ];

    /** The schemes that run code or hold a document; never allowed in a link. */
    public const array DENIED_SCHEMES = ['javascript', 'vbscript', 'livescript', 'mocha', 'data', 'file'];

    public const array DEFAULT_ELEMENTS = [
        // Structure.
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'switch', 'view', 'a',
        // Shapes.
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        // Text.
        'text', 'tspan', 'textPath',
        // Paint servers, clipping, masking and markers.
        'linearGradient', 'radialGradient', 'stop', 'pattern', 'clipPath', 'mask', 'marker',
        // Raster images, in `data:` addresses.
        'image',
        // Filters.
        'filter', 'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix',
        'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA', 'feFuncB',
        'feFuncG', 'feFuncR', 'feGaussianBlur', 'feImage', 'feMerge', 'feMergeNode', 'feMorphology', 'feOffset',
        'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence',
    ];

    /** The elements of SMIL animation, which `withAnimations()` allows. */
    public const array ANIMATION_ELEMENTS = ['animate', 'animateMotion', 'animateTransform', 'set', 'mpath'];

    /** The attributes of SMIL animation, which `withAnimations()` allows. */
    public const array ANIMATION_ATTRIBUTES = [
        'attributeName', 'attributeType', 'begin', 'dur', 'end', 'min', 'max', 'restart', 'repeatCount', 'repeatDur',
        'calcMode', 'keyTimes', 'keySplines', 'keyPoints', 'from', 'to', 'by', 'additive', 'accumulate', 'path',
    ];

    /**
     * The presentation attributes: properties of CSS written as attributes, whose values are read as CSS and go
     * through the CSS policy when they hold more than names, numbers and colours.
     */
    public const array PRESENTATION_ATTRIBUTES = [
        'alignment-baseline', 'baseline-shift', 'clip', 'clip-path', 'clip-rule', 'color', 'color-interpolation',
        'color-interpolation-filters', 'color-rendering', 'direction', 'display', 'dominant-baseline', 'fill',
        'fill-opacity', 'fill-rule', 'filter', 'flood-color', 'flood-opacity', 'font', 'font-family', 'font-size',
        'font-size-adjust', 'font-stretch', 'font-style', 'font-variant', 'font-weight', 'image-rendering',
        'letter-spacing', 'lighting-color', 'marker', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'mask-type',
        'opacity', 'overflow', 'paint-order', 'shape-rendering', 'stop-color', 'stop-opacity', 'stroke',
        'stroke-dasharray', 'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit',
        'stroke-opacity', 'stroke-width', 'text-anchor', 'text-decoration', 'text-rendering', 'transform',
        'transform-origin', 'unicode-bidi', 'vector-effect', 'visibility', 'word-spacing', 'writing-mode',
    ];

    public const array DEFAULT_ATTRIBUTES = [
        // Core.
        'id', 'class', 'style', 'lang', 'xml:lang', 'xml:space', 'systemLanguage',
        // References and links.
        'href', 'xlink:href', 'xlink:title',
        // Viewports and geometry.
        'version', 'viewBox', 'preserveAspectRatio', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy', 'r', 'rx', 'ry',
        'fx', 'fy', 'fr', 'width', 'height', 'd', 'points', 'pathLength',
        // Paint servers, clipping, masking and markers.
        'gradientUnits', 'gradientTransform', 'spreadMethod', 'offset', 'patternUnits', 'patternContentUnits',
        'patternTransform', 'clipPathUnits', 'maskUnits', 'maskContentUnits', 'markerUnits', 'markerWidth',
        'markerHeight', 'refX', 'refY', 'orient',
        // Text.
        'dx', 'dy', 'rotate', 'textLength', 'lengthAdjust', 'startOffset', 'method', 'spacing', 'side',
        // Filters.
        'filterUnits', 'primitiveUnits', 'in', 'in2', 'result', 'mode', 'type', 'values', 'tableValues', 'slope',
        'intercept', 'amplitude', 'exponent', 'operator', 'k1', 'k2', 'k3', 'k4', 'order', 'kernelMatrix', 'divisor',
        'bias', 'targetX', 'targetY', 'edgeMode', 'kernelUnitLength', 'preserveAlpha', 'surfaceScale',
        'diffuseConstant', 'specularConstant', 'specularExponent', 'scale', 'xChannelSelector', 'yChannelSelector',
        'stdDeviation', 'radius', 'baseFrequency', 'numOctaves', 'seed', 'stitchTiles', 'azimuth', 'elevation', 'z',
        'pointsAtX', 'pointsAtY', 'pointsAtZ', 'limitingConeAngle',
        ...self::PRESENTATION_ATTRIBUTES,
    ];

    public const array DEFAULT_LINK_SCHEMES = ['http', 'https', 'mailto'];

    /** The prefix of every `id` and of every reference to one, by default: no script names `svg-…` without quotes. */
    public const string DEFAULT_ID_PREFIX = 'svg-';

    /** The properties of SVG that the CSS policy of an image allows besides those of a page. */
    public const array CSS_PROPERTIES = [
        'alignment-baseline', 'baseline-shift', 'clip', 'clip-rule', 'color-interpolation', 'color-interpolation-filters',
        'color-rendering', 'dominant-baseline', 'fill', 'fill-opacity', 'fill-rule', 'flood-color', 'flood-opacity',
        'image-rendering', 'lighting-color', 'marker', 'marker-end', 'marker-mid', 'marker-start', 'mask', 'mask-type',
        'paint-order', 'shape-rendering', 'stop-color', 'stop-opacity', 'stroke', 'stroke-dasharray',
        'stroke-dashoffset', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-opacity', 'stroke-width',
        'text-anchor', 'text-rendering', 'transform-box', 'vector-effect',
    ];

    /** @var array<string, true> */
    public array $elements;

    /** @var array<string, true> attribute names, those of the XML and XLink namespaces with their `xml:` and `xlink:` prefixes */
    public array $attributes;

    /** @var array<string, true> in lower case */
    public array $linkSchemes;

    public CssPolicy $css;

    /**
     * @param CssPolicy|null $css           the CSS policy of `style` attributes and `<style>` elements; a page's policy
     *                                      with the properties of SVG, every URL but a fragment refused, by default
     * @param string|null    $idPrefix      written before every `id` and every reference to one; `null` keeps them as
     *                                      written
     * @param bool           $styleElements whether `<style>` elements are kept, as `withStyleElements()` says
     */
    public function __construct(
        ?CssPolicy $css = null,
        public bool $dataImages = true,
        public int $maxBytes = 4_194_304,
        public int $maxDepth = 64,
        public int $maxElements = 50_000,
        public int $maxAttributes = 256,
        public ?string $idPrefix = self::DEFAULT_ID_PREFIX,
        bool $styleElements = false,
    ) {
        $this->atLeastOne('maximum size', $maxBytes);
        $this->atLeastOne('maximum depth', $maxDepth);
        $this->atLeastOne('maximum number of elements', $maxElements);
        $this->atLeastOne('maximum number of attributes', $maxAttributes);

        $this->css = $css ?? new CssPolicy()->withProperties(...self::CSS_PROPERTIES);
        $this->elements = array_fill_keys($styleElements ? [...self::DEFAULT_ELEMENTS, 'style'] : self::DEFAULT_ELEMENTS, true);
        $this->attributes = array_fill_keys(self::DEFAULT_ATTRIBUTES, true);
        $this->linkSchemes = array_fill_keys(self::DEFAULT_LINK_SCHEMES, true);
    }

    public function withElements(string ...$elements): self
    {
        foreach ($elements as $element) {
            if (self::isDeniedElement($element)) {
                throw InvalidPolicy::deniedElement($element);
            }
        }

        return clone ($this, ['elements' => $this->elements + array_fill_keys($elements, true)]);
    }

    public function withoutElements(string ...$elements): self
    {
        return clone ($this, ['elements' => array_diff_key($this->elements, array_fill_keys($elements, true))]);
    }

    /** Attribute names, written as SVG writes them; `xlink:` and `xml:` name the attributes of those namespaces. */
    public function withAttributes(string ...$attributes): self
    {
        foreach ($attributes as $attribute) {
            if (self::isDeniedAttribute($attribute)) {
                throw InvalidPolicy::deniedAttribute($attribute);
            }
        }

        return clone ($this, ['attributes' => $this->attributes + array_fill_keys($attributes, true)]);
    }

    public function withoutAttributes(string ...$attributes): self
    {
        return clone ($this, ['attributes' => array_diff_key($this->attributes, array_fill_keys($attributes, true))]);
    }

    /**
     * SMIL animation: its elements and attributes. An animation that targets a reference, a style or an event
     * handler, or whose values hold a function or a scheme, is removed all the same.
     */
    public function withAnimations(): self
    {
        return $this->withElements(...self::ANIMATION_ELEMENTS)->withAttributes(...self::ANIMATION_ATTRIBUTES);
    }

    /** The schemes the links of `<a>` elements may use, in place of the current ones; none, to keep only fragments. */
    public function withLinkSchemes(string ...$schemes): self
    {
        foreach ($schemes as $scheme) {
            if (\in_array(strtolower($scheme), self::DENIED_SCHEMES, true)) {
                throw InvalidPolicy::deniedScheme($scheme);
            }
        }

        return clone ($this, ['linkSchemes' => array_fill_keys(array_map(strtolower(...), $schemes), true)]);
    }

    /**
     * The prefix written before every `id` and every fragment that refers to one — `href="#a"`, `url(#a)` —, or `null`
     * to keep them as written. An image written inline shares the page: its ids name global variables of the page's
     * scripts and answer `getElementById()`, unless a prefix sets them apart. It is the application's, and is written as
     * given.
     */
    public function withIdPrefix(?string $prefix): self
    {
        return clone ($this, ['idPrefix' => $prefix]);
    }

    /**
     * Whether `<style>` elements are kept — the same as allowing or removing the element `style`. Their text goes
     * through the CSS policy as a style sheet, and every rule is placed under the image's root, named by an `id`
     * written from the image's content, which replaces its own, so that two images whose roots share an `id` never
     * share a rule: no rule reaches the rest of the page, nor the root's siblings. `@import`, `@page` and
     * `@font-face` are removed, and every URL but a fragment, whatever the CSS policy allows elsewhere.
     */
    public function withStyleElements(bool $allowed): self
    {
        return $allowed ? $this->withElements('style') : $this->withoutElements('style');
    }

    public function withCss(CssPolicy $css): self
    {
        return clone ($this, ['css' => $css]);
    }

    /** Whether `<image>` and `<feImage>` may show a PNG, JPEG, GIF, WebP or AVIF written in a `data:` address. */
    public function withDataImages(bool $allowed): self
    {
        return clone ($this, ['dataImages' => $allowed]);
    }

    /** The largest image accepted, in bytes; a larger one is refused whole. */
    public function withMaxBytes(int $bytes): self
    {
        $this->atLeastOne('maximum size', $bytes);

        return clone ($this, ['maxBytes' => $bytes]);
    }

    /** The deepest nesting of elements kept; an image nested deeper is refused whole. */
    public function withMaxDepth(int $depth): self
    {
        $this->atLeastOne('maximum depth', $depth);

        return clone ($this, ['maxDepth' => $depth]);
    }

    /** The most elements an image may hold, kept or not; an image with more is refused whole. */
    public function withMaxElements(int $elements): self
    {
        $this->atLeastOne('maximum number of elements', $elements);

        return clone ($this, ['maxElements' => $elements]);
    }

    /**
     * The most attributes one element may hold, kept or not, counted before the image is parsed; an image with an
     * element that holds more is refused whole.
     */
    public function withMaxAttributes(int $attributes): self
    {
        $this->atLeastOne('maximum number of attributes', $attributes);

        return clone ($this, ['maxAttributes' => $attributes]);
    }

    public function allowsElement(string $element): bool
    {
        return isset($this->elements[$element]);
    }

    public function allowsStyleElements(): bool
    {
        return $this->allowsElement('style');
    }

    public function allowsAttribute(string $attribute): bool
    {
        return isset($this->attributes[$attribute]);
    }

    public function allowsLinkScheme(string $scheme): bool
    {
        return isset($this->linkSchemes[strtolower($scheme)]);
    }

    public static function isDeniedElement(string $element): bool
    {
        return \in_array(strtolower($element), self::DENIED_ELEMENTS, true);
    }

    public static function isDeniedAttribute(string $attribute): bool
    {
        $attribute = strtolower($attribute);

        return str_starts_with($attribute, 'on') || 'xml:base' === $attribute;
    }

    private function atLeastOne(string $name, int $value): void
    {
        if ($value < 1) {
            throw InvalidPolicy::limit($name, $value);
        }
    }
}
