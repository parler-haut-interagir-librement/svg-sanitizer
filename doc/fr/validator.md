<!-- phil-dev source-sha256=5cd9453dff9727971eb9c57f8b395aa4966294128fd9f033541f6dce6c8db247 -->
# Validateur

`Validator\SafeSvg` est une contrainte pour `symfony/validator` : la valeur, une chaîne ou un `Stringable`, est une
image SVG que l'assainisseur conserve en entier. Chaque retrait listé est une violation, et les retraits au-delà des 100
premiers en font une de plus ; une image refusée en entier — trop
grande, mal formée, qui n'est pas du SVG, qui déclare des entités — est une seule violation. `null` et la chaîne vide
sont valides.

```php
use Phil\SvgSanitizer\Validator\SafeSvg;

final class Logo
{
    #[SafeSvg]
    public string $svg = '';
}
```

| Option | Valeur par défaut | Sens |
|---|---|---|
| `message` | `This SVG image holds {{ removed }} on line {{ line }}: {{ subject }}.` | Le message de chaque retrait |
| `refusedMessage` | `This SVG image is refused: {{ removed }}.` | Le message d'une image refusée en entier |
| `moreMessage` | `This SVG image holds {{ count }} more parts that are not allowed.` | Le message des retraits au-delà des 100 premiers |

Les paramètres de chaque violation sont `{{ removed }}` (`RemovalReason::describe()`), `{{ subject }}`, `{{ line }}`
et `{{ reason }}` (la valeur de la raison, comme `event_handler`). Le code est `SafeSvg::NOT_ALLOWED_ERROR` pour un
retrait et `SafeSvg::REFUSED_ERROR` pour une image refusée en entier. La violation des retraits au-delà des 100 premiers
a `{{ count }}` pour seul paramètre, le même nombre pour son pluriel, et le code `SafeSvg::NOT_ALLOWED_ERROR` : une
valeur de quelques mégaoctets donne au plus 101 violations.

La politique est celle du `SvgSanitizer` donné à `SafeSvgValidator`. Sans déclaration de service, la fabrique du
validateur le construit avec la politique par défaut ; déclarez-le comme service pour utiliser la politique de
l'application, comme le montre [Installation](installation.md).

Un formulaire peut valider avec `SafeSvg` et conserver l'image telle qu'écrite, puisque l'assainisseur la conserverait
en entier. Il peut aussi se passer de la contrainte et conserver ce que retourne `sanitize()`, en indiquant à l'auteur
ce qui a été retiré. Une image validée une fois est quand même assainie de nouveau quand la politique change : la
contrainte vérifie la valeur selon la politique du moment.

## Messages et traductions

Les violations portent leurs nombres sous forme d'entiers — `{{ line }}` de chaque retrait et `{{ count }}` des
retraits au-delà des 100 premiers — et les autres paramètres sous forme de chaînes. Les messages par défaut sont les
clés de traduction, dans le domaine `validators`.

La bibliothèque fournit des catalogues en ICU MessageFormat, dans le domaine `validators+intl-icu`, dans
`translations/` :

| Locale | Messages |
|---|---|
| `en` | `moreMessage`, dont le pluriel suit `{{ count }}`. |
| `fr` | Chaque message par défaut : ce qui a été retiré suit `{{ reason }}`, et le pluriel de `moreMessage` suit `{{ count }}`. |

```text
This SVG image holds {{ count }} more parts that are not allowed.
→ {count, plural, one {This SVG image holds # more part that is not allowed.} other {This SVG image holds # more parts that are not allowed.}}
```

`{{ removed }}` est `RemovalReason::describe()`, en anglais : le catalogue français nomme plutôt chaque raison à partir
de `{{ reason }}`, et se rabat sur `{{ removed }}` pour une raison qu'il ne connaît pas.

Les catalogues sont lus dès que l'application donne leur répertoire au traducteur de FrameworkBundle
([Installation](installation.md#traductions)) ; le domaine ICU a besoin de `MessageFormatter`, de `ext-intl` ou de
`symfony/polyfill-intl-messageformatter`. Sans eux, les messages restent tels qu'écrits. Le répertoire `translations/`
de l'application vient après celui de la bibliothèque : un message qu'elle y traduit, dans le domaine `validators` ou
`validators+intl-icu`, remplace celui de la bibliothèque. Un message donné à la contrainte (`moreMessage: '…'`) est
traduit comme n'importe quelle clé du domaine ; écrit en ICU, il appartient à un catalogue ICU de l'application.
