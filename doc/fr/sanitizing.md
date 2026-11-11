<!-- phil-dev source-sha256=71ed48a3534915338c4ff2d78ceb98274f66def34405b7d571607674ce0db871 -->
# Assainir

## Une image

`SvgSanitizer::sanitize()` prend le contenu d'un fichier `.svg`, ou un élément `<svg>` écrit en XML, et retourne un
`Sanitized` :

```php
$sanitized = $sanitizer->sanitize(file_get_contents($upload->getPathname()));

if ($sanitized->isRefused()) {
    // Nothing to store: the one removal says why.
}

file_put_contents($target, $sanitized->svg);
```

| Membre | Sens |
|---|---|
| `svg` | L'image : un élément `<svg>` dans l'espace de noms SVG, sans déclaration XML ; vide quand elle est refusée en entier |
| `removals` | Chaque `Removal`, dans l'ordre de la source, jusqu'à `Sanitized::MAX_REMOVALS` (100) |
| `omittedRemovals` | Les retraits au-delà des 100 premiers, comptés mais non listés |
| `isClean()` | Si rien n'a été retiré : l'image a été permise telle quelle |
| `isRefused()` | Si l'image a été refusée en entier |

La sortie est construite à partir d'un document vide : chaque élément de la source que permet la politique est recréé,
avec les attributs que permet la politique et son texte ; tout le reste est laissé derrière. Les commentaires, la
déclaration XML et le type de document sont omis sans retrait signalé. Une image dont l'élément racine n'a pas
d'espace de noms est lue comme du SVG et écrite dans l'espace de noms SVG, comme c'est souvent le cas d'un `<svg>`
tiré d'une page HTML.

Un élément retiré emporte son contenu : le texte d'un `<script>`, les enfants d'un `<metadata>` ou d'un élément d'un
autre espace de noms n'atteignent jamais la sortie.

## Retraits

Une image de quelques mégaoctets peut contenir des centaines de milliers d'attributs à retirer : les 100 premiers
retraits sont listés, dans l'ordre de la source, ceux de son CSS compris, et `omittedRemovals` compte les autres. Une
image dont les retraits dépassent les 100 premiers n'est pas plus refusée qu'une image qui en a moins.

Un `Removal` contient une `RemovalReason`, un sujet — le nom de l'élément ou de l'attribut, l'adresse, ou l'attribut et
le sujet CSS, coupé à 80 caractères — et la ligne de la source où commence son élément.

| Raison | Sujet | Ce qui est retiré |
|---|---|---|
| `Element` | `metadata`, `i:named` | Un élément que la politique ne permet pas, ou un élément d'un autre espace de noms, avec son contenu |
| `DeniedElement` | `script` | Un élément qui exécute du code ou intègre un autre document, avec son contenu |
| `Attribute` | `data-x`, `xml:base` | Un attribut que la politique ne permet pas, d'un autre espace de noms, ou avec un préfixe non déclaré |
| `EventHandler` | `onclick` | Un gestionnaire d'événements |
| `ExternalReference` | `other.svg#a`, `fill: http://x.test/p` | Un `href` ou un `url()` qui n'est pas un fragment |
| `DangerousUrl` | `javascript:alert(1)` | Une adresse dont le schéma exécute du code ou contient un document |
| `Link` | `ftp://x.test/` | Un lien dont la politique ne permet pas le schéma, ou un lien relatif |
| `Animation` | `set href`, `to: url(x)` | Une animation d'une référence, d'un style ou d'un gestionnaire, avec son élément ; une valeur d'animation qui contient une fonction ou un schéma |
| `Style` | `style: position` | Du CSS que la politique CSS a retiré, sous l'attribut ou l'élément qui le contenait |
| `ProcessingInstruction` | `xml-stylesheet` | Une instruction de traitement |

Certaines raisons refusent l'image en entier : `svg` est vide, et le retrait est le seul. `RemovalReason::refusesWhole()`
les distingue.

| Raison | Quand |
|---|---|
| `TooLarge` | L'image est plus grande que `maxBytes` |
| `EntityDeclaration` | L'image déclare une entité |
| `Malformed` | L'image n'est pas du XML bien formé en UTF-8, contient un octet nul, ou est vide |
| `NotSvg` | L'élément racine n'est pas `<svg>`, dans l'espace de noms SVG ou dans aucun |
| `TooManyElements` | L'image contient plus de `maxElements` éléments, conservés ou non |
| `TooManyAttributes` | Un élément contient plus de `maxAttributes` attributs, conservés ou non ; compté avant l'analyse de l'image |
| `TooDeep` | Les éléments conservés s'imbriquent plus profondément que `maxDepth` |

`describe()` donne chaque raison en quelques mots, pour un message :

```php
foreach ($sanitized->removals as $removal) {
    $messages[] = sprintf('Line %d: %s (%s).', $removal->line, ucfirst($removal->reason->describe()), $removal->subject);
}
```
