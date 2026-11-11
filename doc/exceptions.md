# Exceptions

Every exception of the library implements `Phil\SvgSanitizer\Exception\SvgSanitizerException`.

| Exception | When |
|---|---|
| `InvalidPolicy` | A policy is asked to allow an element, an attribute or a link scheme that is never allowed, or given a limit below 1 |

Sanitizing never throws: an image that cannot be read, is too large or declares entities is refused whole, and the
removal says why.
