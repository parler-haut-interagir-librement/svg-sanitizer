<!-- phil-dev source-sha256=2a1b0c1f994c9d3a9897388a1f9c78c5a32d355f81d7a9751d44c69ee4973e78 -->
# Points d'extension

Ce qu'une application implémente, remplace ou utilise.

## La politique

`Policy` est l'unique point de configuration : éléments, attributs, schémas de lien, images matricielles dans des
adresses `data:` et limites, chacun par un `with…()`. Voir [Politique](policy.md).

## La politique CSS

Le CSS d'une image est vérifié par une `Phil\CssSanitizer\Policy`, donnée avec `Policy::withCss()`. Son
`Phil\CssSanitizer\UrlRewriter` décide du sort des URL de `url()` qui ne sont pas des fragments — refusées par défaut.
Une application qui en permet certaines fournit son propre réécriveur, comme pour n'importe quelle feuille de style.

## Le résultat

`Sanitized`, `Removal` et `RemovalReason` sont des valeurs qu'une application lit : `RemovalReason` est une
énumération adossée à des chaînes dont les valeurs sont stables, pour qu'un rapport puisse être conservé et traduit à
partir d'elles.

## Le validateur

`Validator\SafeSvgValidator` prend le `SvgSanitizer` avec lequel il vérifie : une application l'enregistre comme
service avec son propre assainisseur, avec l'étiquette `validator.constraint_validator`, pour que la contrainte
`SafeSvg` suive la politique de l'application. Voir [Validateur](validator.md).
