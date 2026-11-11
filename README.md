# phil/svg-sanitizer

**Let people upload and paste SVG images without letting them run code, load what they should not, or embed another document.**

phil/svg-sanitizer reads an SVG image as XML — without network, without a document type, refusing entity
declarations — and writes it anew, element by element and attribute by attribute, from allow lists. Scripts,
`foreignObject`, frames and the other elements that run code or embed a document are left out with their content;
event handlers are dropped; every `href` must point to a fragment of the image itself, or be a raster image in a
`data:` address on `<image>`, or a link of an allowed scheme on `<a>`; `style` attributes, the `<style>` elements a
policy allows and the presentation attributes that hold more than names, numbers and colours go through
`phil/css-sanitizer`. Limits of size, depth, number of elements and attributes per element refuse an image whole
before it costs anything. What is removed is listed, the first hundred removals with their reason and line, for the
author to read.

It is for any PHP application that accepts SVG images from people: an import of word processing documents, a media
library, an avatar or a logo upload, a block editor whose authors paste drawings. The output is safe to serve as an
`.svg` file and, under the default policy — which leaves `<style>` out and prefixes every `id` with `svg-` —, to
write inline in a page that other people read.

It is a plain PHP library built on PHP's `dom` and `libxml` extensions, `ext-mbstring` and
`phil/css-sanitizer`. Nothing is filtered by regular expression on text: the image is parsed, and the output is built
from an empty document, so that whatever the policy does not name never reaches it.

The design is small: `SvgSanitizer` sanitizes against a `Policy` and returns a `Sanitized` result — the SVG and the
`Removal`s, each with its `RemovalReason`. With `symfony/validator` installed, `Validator\SafeSvg` turns the removals
into violations.

## Status

| Part | State |
|---|---|
| XML read without network, document type nor entities; processing instructions and comments left out | Implemented and tested |
| Allow lists of elements and attributes, SVG and XLink names as written; elements of other namespaces left out | Implemented and tested |
| Elements that run code or embed a document, event handlers and `xml:base` never allowed | Implemented and tested |
| References: fragments, raster images in `data:` addresses, links of allowed schemes; scheme tricks caught | Implemented and tested |
| `style` attributes, `<style>` elements and presentation attributes through `phil/css-sanitizer` | Implemented and tested |
| Safe inline by default: `<style>` left out, every `id` and the fragments that point to it prefixed | Implemented and tested |
| `<style>` kept by `withStyleElements(true)`, every rule held under the image's root | Implemented and tested |
| SMIL animation under the policy, never of a reference, a style or an event handler | Implemented and tested |
| Limits of size, depth, number of elements and attributes per element; the first 100 removals reported with their reason and line, the others counted | Implemented and tested |
| Symfony validator constraint `SafeSvg` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Sanitizing](doc/sanitizing.md) | Sanitizing an image, the result, the removals and their reasons. |
| [Policy](doc/policy.md) | What the default policy allows, every `with…()`, and the limits. |
| [References](doc/references.md) | `href`, `url()`, links and `data:` addresses. |
| [Threats](doc/threats.md) | What the sanitizer protects from, how, and what stays the application's. |
| [Validator](doc/validator.md) | The `SafeSvg` constraint. |
| [Extension points](doc/extension-points.md) | The policy, the CSS policy and its rewriter, the result. |
| [Exceptions](doc/exceptions.md) | `SvgSanitizerException` and `InvalidPolicy`. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that sanitizes SVG. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/svg-sanitizer
```

```php
use Phil\SvgSanitizer\SvgSanitizer;

$sanitized = new SvgSanitizer()->sanitize(<<<'SVG'
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">
      <script>alert(2)</script>
      <use href="https://example.com/sprite.svg#icon"/>
      <rect width="10" height="10" fill="url(#paper)" style="fill: red; position: fixed"/>
    </svg>
    SVG);

echo $sanitized->svg;
// <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
//
//   <use/>
//   <rect width="10" height="10" fill="url(#svg-paper)" style="fill: red"/>
// </svg>

foreach ($sanitized->removals as $removal) {
    printf("line %d: %s (%s)\n", $removal->line, $removal->reason->describe(), $removal->subject);
}
// line 1: an event handler (onload)
// line 2: an element that runs code or embeds another document (script)
// line 3: a reference to something outside the image (https://example.com/sprite.svg#icon)
// line 4: CSS that is not allowed (style: position)
```

## Requirements

PHP 8.5 or later, with the `dom`, `libxml` and `mbstring` extensions; `phil/css-sanitizer`; `symfony/validator` for
the `SafeSvg` constraint.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
