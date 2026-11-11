# Policy

`new Policy()` allows what a static drawing needs, and nothing that moves or reacts. It is safe for an image written
inline in a page: it leaves out `<style>` and prefixes every `id`, as [Inline images](#inline-images) explains;
`withStyleElements(true)` keeps `<style>`, its rules held inside the image ([Style elements](#style-elements)). Each
`with…()` returns a new policy; a policy is a value, safe to share. Element and attribute names are compared as
written, since SVG names are case-sensitive: `linearGradient`, `viewBox`.

## Elements

| Group | Elements |
|---|---|
| Structure | `svg`, `g`, `defs`, `symbol`, `use`, `title`, `desc`, `switch`, `view`, `a` |
| Shapes | `path`, `rect`, `circle`, `ellipse`, `line`, `polyline`, `polygon` |
| Text | `text`, `tspan`, `textPath` |
| Paint servers, clipping, masking, markers | `linearGradient`, `radialGradient`, `stop`, `pattern`, `clipPath`, `mask`, `marker` |
| Raster images | `image` |
| Filters | `filter` and every filter primitive, `feBlend` to `feTurbulence` |

`withElements()` and `withoutElements()` add and remove names. `withAnimations()` adds the elements of SMIL
animation — `animate`, `animateMotion`, `animateTransform`, `set`, `mpath` — and their timing and value attributes.

**Never allowed**, whatever the policy says, compared in any case: `script`, `foreignObject`, `iframe`, `object`,
`embed`, `handler`, `listener`, `audio`, `video`, `canvas`, `font-face-uri`, `font-face-src`, `cursor`, `tref`.
Asking for one throws `InvalidPolicy`.

## Attributes

The default attributes are the core ones (`id`, `class`, `style`, `lang`, `xml:lang`, `xml:space`,
`systemLanguage`), the references (`href`, `xlink:href`, `xlink:title`), the geometry of every allowed element, the
units, transforms and coordinates of paint servers, clipping, masks and markers, the attributes of text and of every
filter primitive, and the presentation attributes (`Policy::PRESENTATION_ATTRIBUTES`: `fill`, `stroke`, `opacity`,
`font-family`, `transform`…).

`withAttributes()` and `withoutAttributes()` add and remove names; `xml:` and `xlink:` name the attributes of the XML
and XLink namespaces. An attribute of any other namespace — those of drawing programs, `inkscape:` or `sodipodi:` —
is removed.

**Never allowed**: every name that starts with `on`, in any case, and `xml:base`, which would change where every
reference points.

## Values

Most values are kept as written. Some are checked:

| Attribute | Check |
|---|---|
| `id` | Written after the policy's id prefix, `svg-` by default; see [Inline images](#inline-images) |
| `href`, `xlink:href` | See [References](references.md) |
| `style` | The declarations through the CSS policy; what it keeps is written back |
| Presentation attributes | Kept as written when made of letters, digits, `#`, `%`, `.`, `,`, `+`, `-` and spaces; otherwise read as the value of their property by the CSS policy, and kept as written only when it removes nothing |
| `gradientTransform`, `patternTransform` | As the presentation attribute `transform` |
| `from`, `to`, `by`, `values` of an animation | Removed when they hold `(`, `\` or `:` — a function, an escape or a scheme |
| `attributeName` of an animation | The whole animation is removed when it targets `href`, `xlink:href`, `style`, `xml:base` or an event handler |

## CSS

The CSS policy of the image is a policy of `phil/css-sanitizer`: by default, the policy of a page with the properties
of SVG added (`Policy::CSS_PROPERTIES`: `fill`, `stroke`, `stop-color`, `marker-end`…), and every URL refused but a
fragment. `withCss()`, or the first argument of the constructor, gives another:

```php
use Phil\CssSanitizer\Policy as CssPolicy;

$policy = new Policy()->withCss(new CssPolicy()->withProperties(...Policy::CSS_PROPERTIES)->withoutProperties('filter'));
```

A rewriter given to that policy decides the URLs of the image's CSS as it decides those of any style sheet.

## Inline images

An image written inline in a page shares that page: the rules of a `<style>` element reach the whole page, and each
`id` answers `getElementById()` and names a global variable of the page's scripts — `<rect id="appConfig"/>` stands
in for a `window.appConfig` the page never set. The default policy is safe there:

- **`<style>` is left out**, removed with the reason `Element`. `withStyleElements(true)` keeps it, every rule held
  inside the image, as [Style elements](#style-elements) explains.
- **Every `id` is prefixed** with `svg-`, and so is every fragment that points to one: `href="#a"`, `xlink:href="#a"`,
  `url(#a)` in a `style` attribute, a presentation attribute or a `<style>` element. No script names `svg-a` without
  quotes, and no id of the image takes the name of one of the page's. `withIdPrefix()` sets another prefix — one per
  image keeps two images of a page apart —, and `withIdPrefix(null)` keeps ids as written, for images only ever
  served as files or shown through `<img>`.

A fragment written with escapes, `url(\23 a)`, and the ids that SMIL timing names, `begin="a.end"`, are not
prefixed: they then point to no element, which breaks the drawing and nothing else.

## Style elements

`withStyleElements(true)`, or `new Policy(styleElements: true)`, keeps `<style>` elements — the same as
`withElements('style')`. A diagram drawn by Mermaid writes its theme there, each rule under the id of its root. The
text of each one is read as a style sheet by the CSS policy, held inside the image:

- **Every rule is placed under the root**, through the CSS policy's scope: an id written from the image's content,
  the prefix, `drawing-` and sixteen hexadecimal digits of its SHA-256, such as `svg-drawing-3f0c…`. It replaces the
  root's own `id`, or is given to a root without one when a `<style>` element is kept: two images on one page whose
  roots share an `id`, as two diagrams drawn by Mermaid do, never share a rule, and the same image written twice
  repeats the same rules. Every reference to the root's `id` — `href`, `url()`, selectors — points to the scope.
  `body { display: none }` becomes `#svg-drawing-3f0c… body { … }`, which matches nothing; `:root`, `html` and `body`
  match nothing under the scope either. Rules nested in `@media` and `@supports` are placed under it too.
- **A rule the image writes under its own root keeps its meaning.** `#m .node rect`, in an image whose root is
  `id="m"`, becomes `#svg-drawing-3f0c… .node rect`, and `#m { fill: #333 }` styles the root itself. Such a selector loses the
  scope only when it reaches nothing but the root and what lies inside it: one with `+` or `~`, which reach siblings,
  keeps it, and so does a selector of the root alone whose rule nests other rules, since their `&` would stand for the
  root and reach its siblings.
- **The ids the selectors name are prefixed** as the image's are: `#a` becomes `#svg-a`, inside `:not()` too.
- **Nesting keeps to the CSS policy's rules**: `&` at the top level and `:not(&)` are removed, and a nested rule
  reaches only its parent, the parent's descendants and their siblings, all inside the image.
- **Nothing is loaded and nothing is declared for the page**: `@import`, `@page` and `@font-face` are removed, and
  every URL but a fragment, whatever rewriter the CSS policy has for `style` attributes.

The scope is an id: give each image written in a page a root `id` of its own, and keep the prefix, `svg-` by default,
out of the page's own ids, so that no other element answers to it.

## Links, raster images and ids

| Method | Default | Effect |
|---|---|---|
| `withLinkSchemes(string ...)` | `http`, `https`, `mailto` | The schemes the links of `<a>` may use, in place of the current ones; none keeps only fragments. `javascript`, `vbscript`, `livescript`, `mocha`, `data` and `file` throw `InvalidPolicy` |
| `withDataImages(bool)` | `true` | Whether `<image>` and `<feImage>` may show a PNG, JPEG, GIF, WebP or AVIF image written in a `data:` address |
| `withIdPrefix(?string)` | `svg-` | The prefix of every `id` and of every fragment that points to one; `null` keeps them as written |
| `withStyleElements(bool)` | `false` | Whether `<style>` elements are kept, their rules held inside the image ([Style elements](#style-elements)); `allowsStyleElements()` says so |

## Limits

| Method | Default | Effect |
|---|---|---|
| `withMaxBytes(int)` | 4 MiB | A larger image is refused whole, before it is parsed |
| `withMaxElements(int)` | 50 000 | An image with more elements, kept or not, is refused whole |
| `withMaxDepth(int)` | 64 | An image whose kept elements nest deeper is refused whole |
| `withMaxAttributes(int)` | 256 | An image with an element that holds more attributes, kept or not, is refused whole, before it is parsed |

The constructor takes the same limits, the id prefix and `styleElements` by name: `new Policy(maxBytes: 1_048_576)`.
A limit below 1 throws `InvalidPolicy`.
