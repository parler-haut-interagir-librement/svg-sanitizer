<!-- phil-dev source-sha256=485b3af1e2ca3279f8c7d035e3165841d66c61f73132ed6655291b46395926d6 -->
# Politique

`new Policy()` permet ce dont un dessin statique a besoin, et rien qui bouge ou qui réagit. Elle est sûre pour une
image écrite directement dans une page : elle laisse `<style>` de côté et préfixe chaque `id`, comme l'explique
[Images dans la page](#images-dans-la-page). `withStyleElements(true)` conserve `<style>`, ses règles tenues dans
l'image ([Éléments de style](#éléments-de-style)). Chaque `with…()` retourne une nouvelle politique ; une politique est
une valeur, qui se partage sans risque. Les noms d'éléments et d'attributs sont comparés tels qu'écrits, puisque les
noms SVG sont sensibles à la casse : `linearGradient`, `viewBox`.

## Éléments

| Groupe | Éléments |
|---|---|
| Structure | `svg`, `g`, `defs`, `symbol`, `use`, `title`, `desc`, `switch`, `view`, `a` |
| Formes | `path`, `rect`, `circle`, `ellipse`, `line`, `polyline`, `polygon` |
| Texte | `text`, `tspan`, `textPath` |
| Serveurs de peinture, découpage, masquage, marqueurs | `linearGradient`, `radialGradient`, `stop`, `pattern`, `clipPath`, `mask`, `marker` |
| Images matricielles | `image` |
| Filtres | `filter` et chaque primitive de filtre, de `feBlend` à `feTurbulence` |

`withElements()` et `withoutElements()` ajoutent et retirent des noms. `withAnimations()` ajoute les éléments de
l'animation SMIL — `animate`, `animateMotion`, `animateTransform`, `set`, `mpath` — et leurs attributs de minutage et
de valeur.

**Jamais permis**, quoi que dise la politique, comparés sans égard à la casse : `script`, `foreignObject`, `iframe`,
`object`, `embed`, `handler`, `listener`, `audio`, `video`, `canvas`, `font-face-uri`, `font-face-src`, `cursor`,
`tref`. En demander un lève `InvalidPolicy`.

## Attributs

Les attributs par défaut sont les attributs de base (`id`, `class`, `style`, `lang`, `xml:lang`, `xml:space`,
`systemLanguage`), les références (`href`, `xlink:href`, `xlink:title`), la géométrie de chaque élément permis, les
unités, transformations et coordonnées des serveurs de peinture, du découpage, des masques et des marqueurs, les
attributs du texte et de chaque primitive de filtre, et les attributs de présentation
(`Policy::PRESENTATION_ATTRIBUTES` : `fill`, `stroke`, `opacity`, `font-family`, `transform`…).

`withAttributes()` et `withoutAttributes()` ajoutent et retirent des noms ; `xml:` et `xlink:` nomment les attributs
des espaces de noms XML et XLink. Un attribut de tout autre espace de noms — ceux des logiciels de dessin, `inkscape:`
ou `sodipodi:` — est retiré.

**Jamais permis** : tout nom qui commence par `on`, sans égard à la casse, et `xml:base`, qui changerait la cible de
chaque référence.

## Valeurs

La plupart des valeurs sont conservées telles qu'écrites. Certaines sont vérifiées :

| Attribut | Vérification |
|---|---|
| `id` | Écrit après le préfixe d'identifiant de la politique, `svg-` par défaut ; voir [Images dans la page](#images-dans-la-page) |
| `href`, `xlink:href` | Voir [Références](references.md) |
| `style` | Les déclarations passent par la politique CSS ; ce qu'elle conserve est réécrit |
| Attributs de présentation | Conservés tels qu'écrits quand ils sont faits de lettres, de chiffres, de `#`, `%`, `.`, `,`, `+`, `-` et d'espaces ; sinon lus par la politique CSS comme la valeur de leur propriété, et conservés tels qu'écrits seulement si elle ne retire rien |
| `gradientTransform`, `patternTransform` | Comme l'attribut de présentation `transform` |
| `from`, `to`, `by`, `values` d'une animation | Retirés quand ils contiennent `(`, `\` ou `:` — une fonction, un échappement ou un schéma |
| `attributeName` d'une animation | Toute l'animation est retirée quand elle cible `href`, `xlink:href`, `style`, `xml:base` ou un gestionnaire d'événements |

## CSS

La politique CSS de l'image est une politique de `phil/css-sanitizer` : par défaut, la politique d'une page à laquelle
s'ajoutent les propriétés de SVG (`Policy::CSS_PROPERTIES` : `fill`, `stroke`, `stop-color`, `marker-end`…), et chaque
URL refusée sauf un fragment. `withCss()`, ou le premier argument du constructeur, en donne une autre :

```php
use Phil\CssSanitizer\Policy as CssPolicy;

$policy = new Policy()->withCss(new CssPolicy()->withProperties(...Policy::CSS_PROPERTIES)->withoutProperties('filter'));
```

Un réécriveur donné à cette politique décide du sort des URL du CSS de l'image comme il le fait pour celles de
n'importe quelle feuille de style.

## Images dans la page

Une image écrite directement dans une page partage cette page : les règles d'un élément `<style>` atteignent toute la
page, et chaque `id` répond à `getElementById()` et nomme une variable globale des scripts de la page —
`<rect id="appConfig"/>` tient lieu d'un `window.appConfig` que la page n'a jamais défini. La politique par défaut y
est sûre :

- **`<style>` est laissé de côté**, retiré avec la raison `Element`. `withStyleElements(true)` le conserve, chaque
  règle tenue dans l'image, comme l'explique [Éléments de style](#éléments-de-style).
- **Chaque `id` est préfixé** par `svg-`, comme chaque fragment qui pointe vers lui : `href="#a"`, `xlink:href="#a"`,
  `url(#a)` dans un attribut `style`, un attribut de présentation ou un élément `<style>`. Aucun script ne nomme
  `svg-a` sans guillemets, et aucun identifiant de l'image ne prend le nom d'un de ceux de la page. `withIdPrefix()`
  fixe un autre préfixe — un par image sépare deux images d'une même page —, et `withIdPrefix(null)` garde les
  identifiants tels qu'écrits, pour les images servies seulement en fichier ou montrées par `<img>`.

Un fragment écrit avec des échappements, `url(\23 a)`, et les identifiants que nomme le minutage SMIL,
`begin="a.end"`, ne sont pas préfixés : ils ne pointent alors vers aucun élément, ce qui brise le dessin et rien
d'autre.

## Éléments de style

`withStyleElements(true)`, ou `new Policy(styleElements: true)`, conserve les éléments `<style>` — comme
`withElements('style')`. Un diagramme dessiné par Mermaid y écrit son thème, chaque règle sous l'identifiant de sa
racine. Le texte de chacun est lu comme une feuille de style par la politique CSS, et tenu dans l'image :

- **Chaque règle est placée sous la racine**, par la portée de la politique CSS : un identifiant écrit à partir du
  contenu de l'image, le préfixe, `drawing-` puis seize chiffres hexadécimaux de son SHA-256, comme
  `svg-drawing-3f0c…`. Il remplace l'`id` de la racine, ou est donné à une racine qui n'en a pas quand un élément
  `<style>` est conservé : deux images d'une même page dont les racines partagent un `id`, comme deux diagrammes
  dessinés par Mermaid, ne partagent jamais une règle, et la même image écrite deux fois répète les mêmes règles.
  Chaque référence à l'`id` de la racine — `href`, `url()`, sélecteurs — pointe vers la portée.
  `body { display: none }` devient `#svg-drawing-3f0c… body { … }`, qui ne correspond à rien ; `:root`, `html` et
  `body` ne correspondent à rien sous la portée non plus. Les règles placées dans `@media` et `@supports` passent
  aussi sous la portée.
- **Une règle que l'image écrit sous sa propre racine garde son sens.** `#m .node rect`, dans une image dont la racine
  porte `id="m"`, devient `#svg-drawing-3f0c… .node rect`, et `#m { fill: #333 }` stylise la racine elle-même. Un tel sélecteur
  perd la portée seulement s'il n'atteint que la racine et ce qu'elle contient : un sélecteur avec `+` ou `~`, qui
  atteignent des éléments frères, la garde, tout comme un sélecteur de la racine seule dont la règle en imbrique
  d'autres, puisque leur `&` tiendrait lieu de la racine et atteindrait ses frères.
- **Les identifiants que nomment les sélecteurs sont préfixés** comme ceux de l'image : `#a` devient `#svg-a`, aussi
  dans `:not()`.
- **L'imbrication suit les règles de la politique CSS** : `&` au premier niveau et `:not(&)` sont retirés, et une
  règle imbriquée n'atteint que son parent, les descendants du parent et leurs frères, tous dans l'image.
- **Rien n'est chargé et rien n'est déclaré pour la page** : `@import`, `@page` et `@font-face` sont retirés, ainsi
  que toute URL sauf un fragment, quel que soit le réécriveur que la politique CSS donne aux attributs `style`.

La portée est un identifiant : donnez à chaque image écrite dans une page un `id` de racine qui lui est propre, et
gardez le préfixe, `svg-` par défaut, hors des identifiants de la page, afin qu'aucun autre élément n'y réponde.

## Liens, images matricielles et identifiants

| Méthode | Valeur par défaut | Effet |
|---|---|---|
| `withLinkSchemes(string ...)` | `http`, `https`, `mailto` | Les schémas que peuvent utiliser les liens de `<a>`, à la place des schémas actuels ; aucun schéma ne garde que les fragments. `javascript`, `vbscript`, `livescript`, `mocha`, `data` et `file` lèvent `InvalidPolicy` |
| `withDataImages(bool)` | `true` | Si `<image>` et `<feImage>` peuvent afficher une image PNG, JPEG, GIF, WebP ou AVIF écrite dans une adresse `data:` |
| `withIdPrefix(?string)` | `svg-` | Le préfixe de chaque `id` et de chaque fragment qui pointe vers l'un d'eux ; `null` les garde tels qu'écrits |
| `withStyleElements(bool)` | `false` | Si les éléments `<style>` sont conservés, leurs règles tenues dans l'image ([Éléments de style](#éléments-de-style)) ; `allowsStyleElements()` le dit |

## Limites

| Méthode | Valeur par défaut | Effet |
|---|---|---|
| `withMaxBytes(int)` | 4 Mio | Une image plus grande est refusée en entier, avant d'être analysée |
| `withMaxElements(int)` | 50 000 | Une image qui compte plus d'éléments, conservés ou non, est refusée en entier |
| `withMaxDepth(int)` | 64 | Une image dont les éléments conservés s'imbriquent plus profondément est refusée en entier |
| `withMaxAttributes(int)` | 256 | Une image dont un élément contient plus d'attributs, conservés ou non, est refusée en entier, avant d'être analysée |

Le constructeur prend les mêmes limites, le préfixe d'identifiant et `styleElements` par leur nom :
`new Policy(maxBytes: 1_048_576)`. Une limite inférieure à 1 lève `InvalidPolicy`.
