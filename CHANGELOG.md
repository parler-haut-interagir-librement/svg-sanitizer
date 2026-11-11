# Changelog

All notable changes to `phil/svg-sanitizer` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `SvgSanitizer`: `sanitize()` reads an image as XML without network, document type nor entities, and writes anew,
  in the SVG namespace and without XML declaration, the elements and attributes the policy keeps, their text, and
  nothing else; returns `Sanitized` — the SVG, empty when the image is refused whole — and each `Removal` with its
  `RemovalReason`, subject and line, the first `Sanitized::MAX_REMOVALS` (100) listed and the others counted in
  `omittedRemovals`. An image is read in UTF-8 only: one with a null byte, as UTF-16 and UTF-32 have, or with bytes
  that are not UTF-8 is refused as malformed.
- `Policy`: allow lists of elements and attributes for static drawings — structure, shapes, text, paint servers,
  clipping, masks, markers, filters, raster images —, SMIL animation by `withAnimations()`, the schemes of links,
  raster images in `data:` addresses, the CSS policy of the image, limits of size, depth, number of elements and
  attributes per element (`maxAttributes`, 256 by default, counted in one pass before the image is parsed, refused
  whole as `TooManyAttributes`); safe inline by default: `<style>` left out unless `withStyleElements(true)`,
  `new Policy(styleElements: true)` or `withElements('style')` allows it, and every `id`, with every fragment of
  `href`, `xlink:href` and `url()` that points to one, written after `withIdPrefix()`'s prefix, `svg-` by default,
  `null` to keep them as written; the elements that run code or embed a document (`script`, `foreignObject`,
  `iframe`, `object`, `embed`…), event handlers, `xml:base` and links to `javascript:`, `data:` and their kind can
  never be allowed (`InvalidPolicy`).
- References checked: `href` and `xlink:href` to a fragment of the image; a PNG, JPEG, GIF, WebP or AVIF image in a
  `data:` address on `<image>` and `<feImage>`; a link of an allowed scheme on `<a>`, the whitespace and control
  characters of its scheme ignored as browsers ignore them.
- CSS through `phil/css-sanitizer`: `style` attributes as declarations, allowed `<style>` elements as style sheets
  held inside the image — every rule placed under an id written from the image's content (the prefix, `drawing-`
  and sixteen hexadecimal digits of its SHA-256), which replaces the root's `id`, every reference to it rewritten, so
  that two images on one page whose roots share an `id` never share a rule, a selector that already starts with that `id` kept as the image wrote it when it reaches nothing but the
  root and what lies inside it, the ids of selectors prefixed, `@import`, `@page`, `@font-face` and every URL but a
  fragment removed —, and the presentation attributes and transform lists that hold more than names, numbers and
  colours as the value of their property, every `url()` but a fragment refused.
- Animations that target a reference, a style or an event handler, and values of animation that hold a function or a
  scheme, removed.
- Processing instructions removed and reported; comments and document types left out.
- `SvgSanitizerException`, implemented by `InvalidPolicy`.
- `Validator\SafeSvg`, a `symfony/validator` constraint, and `SafeSvgValidator`: each removal of the sanitizer a
  violation, `NOT_ALLOWED_ERROR`, and an image refused whole one violation, `REFUSED_ERROR`, with the reason, subject
  and line as parameters; the removals past the first 100 one more violation, `moreMessage`, with their count; `null`
  and the empty string valid; the policy the one of the sanitizer the validator is built with.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
