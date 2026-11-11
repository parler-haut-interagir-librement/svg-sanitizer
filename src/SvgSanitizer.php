<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer;

use Dom\Element;
use Dom\XMLDocument;
use DOMException;
use Phil\SvgSanitizer\Internal\Sanitization;
use ValueError;

/**
 * Sanitizes the SVG images people upload or paste: reads them as XML without network, document type nor entities,
 * and writes anew, from an allow list, the elements and attributes the policy keeps.
 *
 * Nothing in the output can run code, react to the reader, embed another document or load anything from outside the
 * image: no script, no event handler, no `foreignObject`, no reference but a fragment of the image itself — and a
 * raster image in a `data:` address when the policy allows it —, no CSS the CSS policy refuses. What is removed is
 * listed, with its reason and line, so that a form can tell its author.
 */
final readonly class SvgSanitizer
{
    /** The namespace of SVG, in which every element of the output is written. */
    public const string SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    public function __construct(
        private Policy $policy = new Policy(),
    ) {
    }

    /** The same sanitizer under another policy. */
    public function withPolicy(Policy $policy): self
    {
        return new self($policy);
    }

    /** An image: the content of an `.svg` file, or an `<svg>` element written as XML. */
    public function sanitize(string $svg): Sanitized
    {
        $bytes = \sprintf('%d bytes', \strlen($svg));

        if (\strlen($svg) > $this->policy->maxBytes) {
            return $this->refused(RemovalReason::TooLarge, $bytes);
        }

        // The image is read as UTF-8, the encoding of the output. A null byte, which XML never admits, betrays UTF-16 or
        // UTF-32, in which a declaration of entities would escape the check below and the parser would warn on bytes it
        // cannot convert.
        if (str_contains($svg, "\0") || !mb_check_encoding($svg, 'UTF-8')) {
            return $this->refused(RemovalReason::Malformed, $bytes);
        }

        // An entity can expand to more than any limit allows, and the XML parser expands those it reads.
        if (false !== stripos($svg, '<!ENTITY')) {
            return $this->refused(RemovalReason::EntityDeclaration, '<!ENTITY');
        }

        // The XML parser checks each attribute of an element against those before it: an element with tens of
        // thousands of them would hold it for seconds. They are counted first, in one pass over the text.
        if ($this->tooManyAttributes($svg)) {
            return $this->refused(RemovalReason::TooManyAttributes, \sprintf('more than %d attributes', $this->policy->maxAttributes));
        }

        try {
            $document = XMLDocument::createFromString($svg, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING);
        } catch (DOMException|ValueError) {
            return $this->refused(RemovalReason::Malformed, $bytes);
        }

        $root = $document->documentElement;

        if (!$root instanceof Element || 'svg' !== $root->localName || !\in_array($root->namespaceURI, [self::SVG_NAMESPACE, null], true)) {
            return $this->refused(RemovalReason::NotSvg, $root->nodeName ?? '');
        }

        $elements = $document->getElementsByTagName('*')->length;

        if ($elements > $this->policy->maxElements) {
            return $this->refused(RemovalReason::TooManyElements, \sprintf('%d elements', $elements));
        }

        return new Sanitization($this->policy, $svg)->document($document, $root);
    }

    /**
     * Whether a start tag holds more attributes than the policy accepts: each `=` of a start tag outside quotes is
     * one. Comments, sections of character data, processing instructions, declarations and end tags are skipped
     * whole, and the text is read once, without regular expression. A `<` outside quotes ends a start tag, as the
     * start of the next one.
     */
    private function tooManyAttributes(string $svg): bool
    {
        $offset = 0;

        while (false !== $offset = strpos($svg, '<', $offset)) {
            $end = match (true) {
                0 === substr_compare($svg, '<!--', $offset, 4) => strpos($svg, '-->', $offset),
                0 === substr_compare($svg, '<![CDATA[', $offset, 9) => strpos($svg, ']]>', $offset),
                0 === substr_compare($svg, '<?', $offset, 2) => strpos($svg, '?>', $offset),
                0 === substr_compare($svg, '<!', $offset, 2), 0 === substr_compare($svg, '</', $offset, 2) => strpos($svg, '>', $offset),
                default => $this->startTag($svg, $offset + 1),
            };

            if (!\is_int($end)) {
                return null === $end;
            }

            $offset = $end;
        }

        return false;
    }

    /**
     * The offset of the `>` or the `<` that ends a start tag, `false` when it is never closed, or `null` when it holds
     * more attributes than the policy accepts.
     */
    private function startTag(string $svg, int $offset): int|false|null
    {
        $attributes = 0;
        $length = \strlen($svg);

        while (($offset += strcspn($svg, '="\'<>', $offset)) < $length) {
            $character = $svg[$offset];

            if ('>' === $character || '<' === $character) {
                return $offset;
            }

            if ('=' === $character) {
                if (++$attributes > $this->policy->maxAttributes) {
                    return null;
                }

                ++$offset;

                continue;
            }

            $closing = strpos($svg, $character, $offset + 1);

            if (false === $closing) {
                return false;
            }

            $offset = $closing + 1;
        }

        return false;
    }

    private function refused(RemovalReason $reason, string $subject): Sanitized
    {
        return new Sanitized('', [new Removal($reason, $subject)]);
    }
}
