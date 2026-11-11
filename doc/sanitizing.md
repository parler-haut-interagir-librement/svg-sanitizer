# Sanitizing

## An image

`SvgSanitizer::sanitize()` takes the content of an `.svg` file, or an `<svg>` element written as XML, and returns a
`Sanitized`:

```php
$sanitized = $sanitizer->sanitize(file_get_contents($upload->getPathname()));

if ($sanitized->isRefused()) {
    // Nothing to store: the one removal says why.
}

file_put_contents($target, $sanitized->svg);
```

| Member | Meaning |
|---|---|
| `svg` | The image: an `<svg>` element in the SVG namespace, without XML declaration; empty when it is refused whole |
| `removals` | Each `Removal`, in the order of the source, up to `Sanitized::MAX_REMOVALS` (100) |
| `omittedRemovals` | The removals past the first 100, counted but not listed |
| `isClean()` | Whether nothing was removed: the image was allowed as it was |
| `isRefused()` | Whether the image was refused whole |

The output is built from an empty document: each element of the source the policy allows is created again, with
the attributes the policy allows and its text; everything else is left behind. Comments, the XML declaration and the
document type are left out without a removal. An image whose root element has no namespace is read as SVG and
written in the SVG namespace, as an `<svg>` taken out of an HTML page often is.

An element that is removed takes its content with it: the text of a `<script>`, the children of a `<metadata>` or of
an element of another namespace never reach the output.

## Removals

An image of a few megabytes can hold hundreds of thousands of attributes to remove: the first 100 removals are
listed, in the order of the source, those of its CSS among them, and `omittedRemovals` counts the others. An image
with removals past the first 100 is no more refused than one with fewer.

A `Removal` holds a `RemovalReason`, a subject — the element or attribute name, the address, or the attribute and the
CSS subject, cut to 80 characters — and the line of the source where its element starts.

| Reason | Subject | What is removed |
|---|---|---|
| `Element` | `metadata`, `i:named` | An element the policy does not allow, or one of another namespace, with its content |
| `DeniedElement` | `script` | An element that runs code or embeds another document, with its content |
| `Attribute` | `data-x`, `xml:base` | An attribute the policy does not allow, of another namespace, or with an undeclared prefix |
| `EventHandler` | `onclick` | An event handler |
| `ExternalReference` | `other.svg#a`, `fill: http://x.test/p` | An `href` or a `url()` that is not a fragment |
| `DangerousUrl` | `javascript:alert(1)` | An address whose scheme runs code or holds a document |
| `Link` | `ftp://x.test/` | A link whose scheme the policy does not allow, or a relative link |
| `Animation` | `set href`, `to: url(x)` | An animation of a reference, a style or a handler, with its element; a value of animation holding a function or a scheme |
| `Style` | `style: position` | CSS the CSS policy removed, under the attribute or element that held it |
| `ProcessingInstruction` | `xml-stylesheet` | A processing instruction |

Some reasons refuse the image whole: `svg` is empty, and the removal is the only one. `RemovalReason::refusesWhole()`
tells them apart.

| Reason | When |
|---|---|
| `TooLarge` | The image is larger than `maxBytes` |
| `EntityDeclaration` | The image declares an entity |
| `Malformed` | The image is not well-formed XML in UTF-8, holds a null byte, or is empty |
| `NotSvg` | The root element is not `<svg>`, in the SVG namespace or in none |
| `TooManyElements` | The image holds more than `maxElements` elements, kept or not |
| `TooManyAttributes` | An element holds more than `maxAttributes` attributes, kept or not; counted before the image is parsed |
| `TooDeep` | The elements kept nest deeper than `maxDepth` |

`describe()` gives each reason in a few words, for a message:

```php
foreach ($sanitized->removals as $removal) {
    $messages[] = sprintf('Line %d: %s (%s).', $removal->line, ucfirst($removal->reason->describe()), $removal->subject);
}
```
