<!-- phil-dev source-sha256=1d4fb264d5672ed2a28a098a812da1f7af0f65bb74ce034fe094ea0890035fa4 -->
# Référence

| Type | Rôle |
|---|---|
| `Phil\SvgSanitizer\SvgSanitizer` | Assainit une image selon une politique ; `SVG_NAMESPACE`, l'espace de noms de SVG |
| `Phil\SvgSanitizer\Policy` | Les éléments, attributs, schémas de lien, images matricielles, la politique CSS et les limites permis ; les listes sous forme de constantes |
| `Phil\SvgSanitizer\Sanitized` | Le résultat : le SVG, les `MAX_REMOVALS` premiers retraits et le compte des autres ; `isClean()`, `isRefused()` |
| `Phil\SvgSanitizer\Removal` | Une partie retirée : sa raison, son sujet et sa ligne |
| `Phil\SvgSanitizer\RemovalReason` | Pourquoi une partie a été retirée ou l'image refusée ; `describe()`, `refusesWhole()` |
| `Phil\SvgSanitizer\Validator\SafeSvg` | La contrainte : une image SVG conservée en entier |
| `Phil\SvgSanitizer\Validator\SafeSvgValidator` | Chaque retrait est une violation, une image refusée en est une |
| `Phil\SvgSanitizer\Exception\SvgSanitizerException` | Implémentée par chaque exception de la bibliothèque |
| `Phil\SvgSanitizer\Exception\InvalidPolicy` | Une politique à qui l'on demande ce qui n'est jamais permis, ou à qui l'on donne une limite inférieure à 1 |

| Constante de `Policy` | Contient |
|---|---|
| `DEFAULT_ELEMENTS`, `DEFAULT_ATTRIBUTES` | Ce que permet `new Policy()` |
| `DENIED_ELEMENTS`, `DENIED_SCHEMES` | Ce qui n'est jamais permis, en minuscules |
| `ANIMATION_ELEMENTS`, `ANIMATION_ATTRIBUTES` | Ce qu'ajoute `withAnimations()` |
| `PRESENTATION_ATTRIBUTES` | Les attributs lus comme du CSS quand ils contiennent plus que des noms, des nombres et des couleurs |
| `CSS_PROPERTIES` | Les propriétés de SVG que la politique CSS par défaut ajoute à celles d'une page |
| `DEFAULT_LINK_SCHEMES` | `http`, `https`, `mailto` |

`Phil\SvgSanitizer\Internal\Sanitization` est interne : une image en cours d'assainissement, jamais utilisée seule.
`Phil\SvgSanitizer\Internal\ScopedStyleSheet` l'est aussi : le texte d'un élément `<style>` tenu sous la racine.
