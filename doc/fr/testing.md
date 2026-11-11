<!-- phil-dev source-sha256=f0fb7c3d8a3331377d0db64ec70e3bd2f193b278c6893cbc4702ae03c70674f8 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste la politique — ses valeurs par défaut, chaque `with…()`, ce qui n'est jamais permis —,
chaque raison de retrait et de refus, les références et leurs ruses de schéma, le CSS des attributs et des éléments
`<style>`, l'animation avec et sans la politique, et les limites à leurs bornes.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/svg-sanitizer/tests
```

Infection maintient la bibliothèque à un score de 100 % de mutants couverts.

## Fuzzing

La cible de fuzzing `svg-sanitizer` donne à l'assainisseur des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/` : quoi qu'il lise, il retourne ce qu'il a conservé ou un refus, et ne lève jamais d'exception ni
d'avertissement. Depuis la racine du monodépôt :

```sh
composer fuzz -- svg-sanitizer
```

## Tester du code qui assainit du SVG

La sortie est déterministe. Comparez le SVG exactement, et les retraits par raison et par sujet :

```php
$sanitized = new SvgSanitizer()->sanitize('<svg><rect onclick="x"/></svg>');

self::assertSame('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>', $sanitized->svg);
self::assertSame(
    [[RemovalReason::EventHandler, 'onclick']],
    array_map(static fn (Removal $removal): array => [$removal->reason, $removal->subject], $sanitized->removals),
);
```

Un test de la politique propre à l'application assainit une image qui contient ce que la politique devrait conserver,
et une autre qui contient ce qu'elle devrait retirer.

Un formulaire qui valide avec `SafeSvg` se teste avec le `ConstraintValidatorTestCase` de Symfony et
`SafeSvgValidator`, comme le fait le test de la bibliothèque elle-même.
