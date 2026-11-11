# Installation

## Requirements

| Requirement | Why |
|---|---|
| PHP 8.5 or later | Readonly classes, typed constants, `clone()` with properties, the `Dom\XMLDocument` API |
| `ext-dom`, `ext-libxml` | The image read as XML and the output built as a new document |
| `ext-mbstring` | The subjects of removals cut on characters |
| `phil/css-sanitizer` | The CSS of `style` attributes, `<style>` elements and presentation attributes |
| `symfony/validator` 8.2 or later, optional | The `SafeSvg` constraint |

## Installing

```sh
composer require phil/svg-sanitizer
```

It is a library, not a bundle: there is nothing to register. Build an `SvgSanitizer` with the application's policy,
once, and keep it; it holds no state between two calls.

```php
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;

$sanitizer = new SvgSanitizer();                                       // the default policy
$animated = new SvgSanitizer(new Policy()->withAnimations());           // a policy of its own
```

In a Symfony application, declare the policy and the sanitizer as services, and the validator with them so that the
`SafeSvg` constraint uses the same policy:

```yaml
services:
    app.svg_policy:
        class: Phil\SvgSanitizer\Policy
        arguments: { $maxBytes: 1048576 }

    Phil\SvgSanitizer\SvgSanitizer:
        arguments: ['@app.svg_policy']

    Phil\SvgSanitizer\Validator\SafeSvgValidator:
        arguments: ['@Phil\SvgSanitizer\SvgSanitizer']
        tags: ['validator.constraint_validator']
```

Without that declaration, the validator is built by the validator's own factory with the default policy.
