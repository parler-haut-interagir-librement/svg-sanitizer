# References

An SVG image can point outside itself in two ways: an `href` (or the older `xlink:href`), and a `url()` in CSS or in a
presentation attribute. The sanitizer keeps only what points inside the image, and the two exceptions a policy
allows.

## `href` and `xlink:href`

| Value | Kept |
|---|---|
| `#paper`, ` #paper ` | Always: a fragment of the image, trimmed, written after the id prefix — `#svg-paper` by default |
| `data:image/png;base64,…` on `<image>` or `<feImage>` | When the policy allows raster images in `data:` addresses: PNG, JPEG, GIF, WebP and AVIF |
| `https://example.com/` on `<a>` | When the policy allows the scheme |
| Anything else | Never |

What is not kept is removed with the reason that fits: `DangerousUrl` when the scheme runs code or holds a document
(`javascript:`, `vbscript:`, `data:` outside a raster image — an SVG in a `data:` address among them), `Link` for
another link of `<a>`, `ExternalReference` for the rest: another file, another site, a relative path.

Browsers drop the whitespace and the control characters inside a scheme, so that `java&#9;script:` runs; the
sanitizer drops them too before it reads the scheme. The value written back is the one of the source, trimmed.

## `url()`

`url()` is read by `phil/css-sanitizer` wherever CSS is: in `style` attributes, in `<style>` elements, and in the
presentation attributes that hold more than names, numbers and colours — `fill="url(#paper)"`,
`mask="url(#fade)"`, `filter="url(#blur)"`. A fragment is kept, written after the id prefix as the ids are; any
other URL goes to the rewriter of the CSS policy, which refuses every one by default. Escapes are resolved before the
function is read: `u\72l(…)` is `url(…)`.

## What the reader's page decides

An image served as a file and shown through `<img>` cannot run a script nor follow a link, whatever it holds; an
image written inline in a page, or opened on its own, can. The sanitizer makes both safe — the default policy keeps
an inline image from styling the page or naming its variables, as [Policy](policy.md#inline-images) explains —; the
`Content-Security-Policy` of the page and the `Content-Disposition` of the file remain the application's — see
[Threats](threats.md).
