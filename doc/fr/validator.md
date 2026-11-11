<!-- phil-dev source-sha256=e5dbd0cde1cc67ace04c9dcc409730d6d9917bc8d9d941a1d3836fe17b0059d5 -->
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
