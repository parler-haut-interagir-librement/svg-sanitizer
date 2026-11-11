# Extension points

What an application implements, replaces or relies on.

## The policy

`Policy` is the one point of configuration: elements, attributes, link schemes, raster images in `data:` addresses
and limits, each through a `with…()`. See [Policy](policy.md).

## The CSS policy

The CSS of an image is checked by a `Phil\CssSanitizer\Policy`, given with `Policy::withCss()`. Its
`Phil\CssSanitizer\UrlRewriter` decides the URLs of `url()` that are not fragments — refused by default; an
application that allows some gives a rewriter of its own, as for any style sheet.

## The result

`Sanitized`, `Removal` and `RemovalReason` are values an application reads: `RemovalReason` is a string-backed enum
whose values are stable, so that a report can be stored and translated by them.

## The validator

`Validator\SafeSvgValidator` takes the `SvgSanitizer` it checks with: an application registers it as a service with
its own sanitizer, tagged `validator.constraint_validator`, for the `SafeSvg` constraint to follow the application's
policy. See [Validator](validator.md).
