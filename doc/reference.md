# Reference

| Type | Role |
|---|---|
| `Phil\SvgSanitizer\SvgSanitizer` | Sanitizes an image against a policy; `SVG_NAMESPACE`, the namespace of SVG |
| `Phil\SvgSanitizer\Policy` | The elements, attributes, link schemes, raster images, CSS policy and limits allowed; the lists as constants |
| `Phil\SvgSanitizer\Sanitized` | The result: the SVG, the first `MAX_REMOVALS` removals and the count of the others; `isClean()`, `isRefused()` |
| `Phil\SvgSanitizer\Removal` | A part removed: its reason, subject and line |
| `Phil\SvgSanitizer\RemovalReason` | Why a part was removed or the image refused; `describe()`, `refusesWhole()` |
| `Phil\SvgSanitizer\Validator\SafeSvg` | The constraint: an SVG image kept whole |
| `Phil\SvgSanitizer\Validator\SafeSvgValidator` | Each removal a violation, a refused image one |
| `Phil\SvgSanitizer\Exception\SvgSanitizerException` | Implemented by every exception of the library |
| `Phil\SvgSanitizer\Exception\InvalidPolicy` | A policy asked for what is never allowed, or given a limit below 1 |

| Constant of `Policy` | Holds |
|---|---|
| `DEFAULT_ELEMENTS`, `DEFAULT_ATTRIBUTES` | What `new Policy()` allows |
| `DENIED_ELEMENTS`, `DENIED_SCHEMES` | What is never allowed, in lower case |
| `ANIMATION_ELEMENTS`, `ANIMATION_ATTRIBUTES` | What `withAnimations()` adds |
| `PRESENTATION_ATTRIBUTES` | The attributes read as CSS when they hold more than names, numbers and colours |
| `CSS_PROPERTIES` | The properties of SVG the default CSS policy adds to a page's |
| `DEFAULT_LINK_SCHEMES` | `http`, `https`, `mailto` |

`Phil\SvgSanitizer\Internal\Sanitization` is internal: one image sanitized, never used on its own.
`Phil\SvgSanitizer\Internal\ScopedStyleSheet` is internal too: the text of a `<style>` element held under the root.
