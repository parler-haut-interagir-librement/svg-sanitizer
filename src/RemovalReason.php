<?php

declare(strict_types=1);

namespace Phil\SvgSanitizer;

/**
 * Why the sanitizer removed part of an image, or refused it whole.
 */
enum RemovalReason: string
{
    /** An element the policy does not allow, or one of another namespace; its content goes with it. */
    case Element = 'element';

    /** An element that runs code, embeds another document or loads a resource; never allowed. */
    case DeniedElement = 'denied_element';

    /** An attribute the policy does not allow, or one of another namespace. */
    case Attribute = 'attribute';

    /** An event handler attribute (`onclick`, `onload`…); never allowed. */
    case EventHandler = 'event_handler';

    /** A reference to something outside the image: an `href` or a `url()` that is not a fragment. */
    case ExternalReference = 'external_reference';

    /** An address whose scheme runs code or holds a document (`javascript:`, `data:` outside an image). */
    case DangerousUrl = 'dangerous_url';

    /** A link to an address whose scheme the policy does not allow. */
    case Link = 'link';

    /** An animation that targets a reference, a style or an event handler, or a value of one that holds a function or a scheme. */
    case Animation = 'animation';

    /** CSS of a `style` attribute, a `<style>` element or a presentation attribute that the CSS policy removed. */
    case Style = 'style';

    /** A processing instruction, such as `<?xml-stylesheet?>`, which can load a style sheet. */
    case ProcessingInstruction = 'processing_instruction';

    /** The image declares entities, which can expand beyond any limit; it is refused whole. */
    case EntityDeclaration = 'entity_declaration';

    /** The image is not well-formed XML; it is refused whole. */
    case Malformed = 'malformed';

    /** The root element of the image is not `<svg>`; it is refused whole. */
    case NotSvg = 'not_svg';

    /** The image is larger than the policy accepts; it is refused whole. */
    case TooLarge = 'too_large';

    /** The image nests elements deeper than the policy accepts; it is refused whole. */
    case TooDeep = 'too_deep';

    /** The image holds more elements than the policy accepts; it is refused whole. */
    case TooManyElements = 'too_many_elements';

    /** An element of the image holds more attributes than the policy accepts; it is refused whole. */
    case TooManyAttributes = 'too_many_attributes';

    /** What was removed, in a few words, for a message. */
    public function describe(): string
    {
        return match ($this) {
            self::Element => 'an element that is not allowed',
            self::DeniedElement => 'an element that runs code or embeds another document',
            self::Attribute => 'an attribute that is not allowed',
            self::EventHandler => 'an event handler',
            self::ExternalReference => 'a reference to something outside the image',
            self::DangerousUrl => 'an address that runs code',
            self::Link => 'a link that is not allowed',
            self::Animation => 'an animation that is not allowed',
            self::Style => 'CSS that is not allowed',
            self::ProcessingInstruction => 'a processing instruction',
            self::EntityDeclaration => 'an image that declares entities',
            self::Malformed => 'an image that is not well-formed XML',
            self::NotSvg => 'a document that is not an SVG image',
            self::TooLarge => 'an image that is too large',
            self::TooDeep => 'elements nested too deep',
            self::TooManyElements => 'an image with too many elements',
            self::TooManyAttributes => 'an element with too many attributes',
        };
    }

    /** Whether the whole image is refused for this reason, rather than a part of it removed. */
    public function refusesWhole(): bool
    {
        return match ($this) {
            self::EntityDeclaration, self::Malformed, self::NotSvg, self::TooLarge, self::TooDeep, self::TooManyElements, self::TooManyAttributes => true,
            default => false,
        };
    }
}
