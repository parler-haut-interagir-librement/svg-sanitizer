<!-- phil-dev source-sha256=da2ad2c0e6976c2ac242ac5fcb0877aae84393aa6baf1745601e63a48a254702 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Les classes en lecture seule, les constantes typées, `clone()` avec propriétés, l'API `Dom\XMLDocument` |
| `ext-dom`, `ext-libxml` | L'image lue comme du XML et la sortie construite comme un nouveau document |
| `ext-mbstring` | Les sujets des retraits coupés sur des caractères |
| `phil/css-sanitizer` | Le CSS des attributs `style`, des éléments `<style>` et des attributs de présentation |
| `symfony/validator` 8.2 ou plus récent, facultatif | La contrainte `SafeSvg` |

## Installation

```sh
composer require phil/svg-sanitizer
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. Construisez un `SvgSanitizer` avec la politique
de l'application, une seule fois, et gardez-le ; il ne conserve aucun état entre deux appels.

```php
use Phil\SvgSanitizer\Policy;
use Phil\SvgSanitizer\SvgSanitizer;

$sanitizer = new SvgSanitizer();                                       // the default policy
$animated = new SvgSanitizer(new Policy()->withAnimations());           // a policy of its own
```

Dans une application Symfony, déclarez la politique et l'assainisseur comme services, et le validateur avec eux, pour
que la contrainte `SafeSvg` utilise la même politique :

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

Sans cette déclaration, le validateur est construit par la fabrique du validateur lui-même, avec la politique par
défaut.
