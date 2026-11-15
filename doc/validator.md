# Validator

`Validator\SafeSvg` is a constraint for `symfony/validator`: the value, a string or a `Stringable`, is an SVG image the
sanitizer keeps whole. Each removal listed is a violation, and the removals past the first 100 are one more; an image
refused whole — too large, malformed, not SVG, declaring
entities — is one violation. `null` and the empty string are valid.

```php
use Phil\SvgSanitizer\Validator\SafeSvg;

final class Logo
{
    #[SafeSvg]
    public string $svg = '';
}
```

| Option | Default | Meaning |
|---|---|---|
| `message` | `This SVG image holds {{ removed }} on line {{ line }}: {{ subject }}.` | The message of each removal |
| `refusedMessage` | `This SVG image is refused: {{ removed }}.` | The message of an image refused whole |
| `moreMessage` | `This SVG image holds {{ count }} more parts that are not allowed.` | The message of the removals past the first 100 |

The parameters of each violation are `{{ removed }}` (`RemovalReason::describe()`), `{{ subject }}`, `{{ line }}` and
`{{ reason }}` (the reason's value, such as `event_handler`). The code is `SafeSvg::NOT_ALLOWED_ERROR` for a removal
and `SafeSvg::REFUSED_ERROR` for an image refused whole. The violation of the removals past the first 100 has
`{{ count }}` as its only parameter, the same count as its plural, and the code `SafeSvg::NOT_ALLOWED_ERROR`: a
value of a few megabytes gives at most 101 violations.

The policy is the one of the `SvgSanitizer` given to `SafeSvgValidator`. Without a service declaration, the
validator's factory builds it with the default policy; declare it as a service to use the application's policy, as
[Installation](installation.md) shows.

A form can validate with `SafeSvg` and store the image as written, since the sanitizer would keep it whole, or skip the
constraint and store what `sanitize()` returns, telling the author what was removed. An image validated once is still
sanitized again when the policy changes: the constraint checks the value against the policy of the moment.

## Messages and translations

The violations carry their numbers as integers — `{{ line }}` of each removal and `{{ count }}` of the removals past
the first 100 — and the other parameters as strings. The default messages are the translation keys, in the
`validators` domain.

The library ships catalogues in ICU MessageFormat, in the `validators+intl-icu` domain, in `translations/`:

| Locale | Messages |
|---|---|
| `en` | `moreMessage`, whose plural follows `{{ count }}`. |
| `fr` | Every default message: what was removed follows `{{ reason }}`, and the plural of `moreMessage` follows `{{ count }}`. |

```text
This SVG image holds {{ count }} more parts that are not allowed.
→ {count, plural, one {This SVG image holds # more part that is not allowed.} other {This SVG image holds # more parts that are not allowed.}}
```

`{{ removed }}` is `RemovalReason::describe()`, in English: the French catalogue names each reason from
`{{ reason }}` instead, and falls back on `{{ removed }}` for a reason it does not know.

The catalogues are read once the application gives their directory to FrameworkBundle's translator
([Installation](installation.md#translations)); the ICU domain needs `MessageFormatter`, of `ext-intl` or of
`symfony/polyfill-intl-messageformatter`. Without them, the messages stay as written. The application's own
`translations/` directory comes after the library's: a message it translates there, in the `validators` or the
`validators+intl-icu` domain, replaces the library's. A message given to the constraint (`moreMessage: '…'`) is
translated as any key of the domain; written in ICU, it belongs in an ICU catalogue of the application.
