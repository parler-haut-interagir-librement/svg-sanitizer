# Threats

| Threat | How the sanitizer answers |
|---|---|
| Script in the image: `<script>`, `<handler>`, `<listener>` | Never allowed; left out with their content |
| Event handlers: `onload`, `onclick`, `onbegin`… | Every attribute whose name starts with `on`, in any case, is removed |
| `javascript:` links, also written `java&#9;script:` or in upper case | The scheme is read as browsers read it, without whitespace nor control characters, and refused |
| Another document embedded: `foreignObject` with HTML, `iframe`, `object`, `embed`, `audio`, `video`, `canvas` | Never allowed; left out with their content |
| A resource loaded from elsewhere: `<use href="…">`, `<image href="…">`, `xml-stylesheet`, `@import`, `url()` in CSS, `font-face-uri`, `cursor`, `tref` | Only fragments are kept; processing instructions removed; CSS through `phil/css-sanitizer`, which refuses every URL but a fragment |
| An SVG inside an SVG, in a `data:` address | `data:` is allowed for PNG, JPEG, GIF, WebP and AVIF only |
| Animation that rewrites a link or a style: `<set attributeName="href" to="javascript:…">` | An animation of `href`, `xlink:href`, `style`, `xml:base` or a handler is removed; values holding a function, an escape or a scheme are removed; animation is off by default |
| `xml:base`, which moves every relative reference | Never allowed |
| Entity expansion — the billion laughs — and external entities | An image that declares an entity is refused before it is parsed; the parser never reaches the network |
| Images that cost more than they show | Limits of size, number of elements, attributes per element and depth refuse the image whole; size and attributes are checked before the image is parsed, since the parser checks each attribute of an element against those before it |
| A form flooded with errors: an image of a few megabytes with hundreds of thousands of attributes to remove | The first 100 removals are listed and the others counted; `SafeSvg` adds one violation for those |
| An image written inline that reaches the page: `<style>body { display: none }</style>`, `id="appConfig"` that stands in for a global variable | The default policy leaves `<style>` out and prefixes every `id`, and every fragment that points to one, with `svg-` |
| A kept `<style>` that reaches past its image: `body`, `:root`, `@media print { nav { … } }`, `#m ~ p`, `#m { & + p { … } }`, `:not(&)`, `@page`, `@font-face`, `@import`, a selector written with escapes | Every rule is placed under the image's root `id` through the CSS policy's scope, escapes read before the check; a selector that already starts with that `id` keeps its own form only when it reaches nothing but the root and what lies inside it; `&` at the top level and `:not(&)` are removed; `@import`, `@page`, `@font-face` and every URL but a fragment are removed |
| Attributes hidden behind an undeclared prefix, elements of another namespace | Removed: the output is built from an empty document, so only what the policy names reaches it |
| CSS that escapes its context: `expression()`, `behavior`, `-moz-binding`, `</style>` | Through `phil/css-sanitizer`, which refuses them and never writes the start of an end tag |

## What stays the application's

- **Serving the file.** An SVG served from the application's own origin and opened on its own runs in that origin.
  Serve uploaded images with `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`, or as
  attachments, or from another origin, as a second line of defence.
- **Writing inline under another policy.** A policy that keeps ids as written, `withIdPrefix(null)`, lets them stand
  in for the page's own; such images belong in `<img>` or in a file. The rules of a kept `<style>` are held under the
  root's `id`: an element of the page with the same `id` — another image with the same root, or one of the page's own
  ids that starts with the prefix — would receive them too. `class` stays as written under every policy: the page's
  rules for a class apply to an inline image that uses it.
- **What the image shows.** A sanitized image is safe, not truthful: it may still look like a button, a form or a
  logo.
