<!-- phil-dev source-sha256=50184e38bb36cb8041bf851fa51b0904b6ea4eab90754347b284b5e694d7a813 -->
# Menaces

| Menace | Réponse de l'assainisseur |
|---|---|
| Script dans l'image : `<script>`, `<handler>`, `<listener>` | Jamais permis ; omis avec leur contenu |
| Gestionnaires d'événements : `onload`, `onclick`, `onbegin`… | Chaque attribut dont le nom commence par `on`, sans égard à la casse, est retiré |
| Liens `javascript:`, aussi écrits `java&#9;script:` ou en majuscules | Le schéma est lu comme le lisent les navigateurs, sans espaces ni caractères de contrôle, et refusé |
| Un autre document intégré : `foreignObject` avec du HTML, `iframe`, `object`, `embed`, `audio`, `video`, `canvas` | Jamais permis ; omis avec leur contenu |
| Une ressource chargée d'ailleurs : `<use href="…">`, `<image href="…">`, `xml-stylesheet`, `@import`, `url()` dans le CSS, `font-face-uri`, `cursor`, `tref` | Seuls les fragments sont conservés ; les instructions de traitement sont retirées ; le CSS passe par `phil/css-sanitizer`, qui refuse toute URL sauf un fragment |
| Un SVG dans un SVG, dans une adresse `data:` | `data:` n'est permis que pour PNG, JPEG, GIF, WebP et AVIF |
| Une animation qui réécrit un lien ou un style : `<set attributeName="href" to="javascript:…">` | Une animation de `href`, `xlink:href`, `style`, `xml:base` ou d'un gestionnaire est retirée ; les valeurs qui contiennent une fonction, un échappement ou un schéma sont retirées ; l'animation est désactivée par défaut |
| `xml:base`, qui déplace chaque référence relative | Jamais permis |
| L'expansion d'entités — le « billion laughs » — et les entités externes | Une image qui déclare une entité est refusée avant d'être analysée ; l'analyseur n'atteint jamais le réseau |
| Des images qui coûtent plus qu'elles ne montrent | Des limites de taille, de nombre d'éléments, d'attributs par élément et de profondeur refusent l'image en entier ; la taille et les attributs sont vérifiés avant l'analyse de l'image, puisque l'analyseur compare chaque attribut d'un élément à ceux qui le précèdent |
| Un formulaire inondé d'erreurs : une image de quelques mégaoctets avec des centaines de milliers d'attributs à retirer | Les 100 premiers retraits sont énumérés et les autres comptés ; `SafeSvg` ajoute une violation pour ceux-là |
| Une image écrite dans la page qui atteint la page : `<style>body { display: none }</style>`, `id="appConfig"` qui tient lieu d'une variable globale | La politique par défaut laisse `<style>` de côté et préfixe par `svg-` chaque `id`, et chaque fragment qui pointe vers l'un d'eux |
| Un `<style>` conservé qui atteint plus loin que son image : `body`, `:root`, `@media print { nav { … } }`, `#m ~ p`, `#m { & + p { … } }`, `:not(&)`, `@page`, `@font-face`, `@import`, un sélecteur écrit avec des échappements | Chaque règle est placée sous l'`id` de la racine de l'image par la portée de la politique CSS, les échappements lus avant la vérification ; un sélecteur qui commence déjà par cet `id` garde sa forme seulement s'il n'atteint que la racine et ce qu'elle contient ; `&` au premier niveau et `:not(&)` sont retirés ; `@import`, `@page`, `@font-face` et toute URL sauf un fragment sont retirés |
| Des attributs cachés derrière un préfixe non déclaré, des éléments d'un autre espace de noms | Retirés : la sortie est construite à partir d'un document vide, et seul ce que la politique nomme l'atteint |
| Du CSS qui s'échappe de son contexte : `expression()`, `behavior`, `-moz-binding`, `</style>` | Par `phil/css-sanitizer`, qui les refuse et n'écrit jamais le début d'une balise fermante |

## Ce qui reste à l'application

- **Servir le fichier.** Un SVG servi depuis l'origine de l'application et ouvert seul s'exécute dans cette origine.
  Servez les images téléversées avec `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`, ou
  comme pièces jointes, ou depuis une autre origine, comme seconde ligne de défense.
- **Écrire dans la page sous une autre politique.** Une politique qui garde les identifiants tels qu'écrits,
  `withIdPrefix(null)`, les laisse tenir lieu de ceux de la page ; de telles images vont dans `<img>` ou dans un
  fichier. Les règles d'un `<style>` conservé sont tenues sous l'`id` de la racine : un élément de la page qui porte le
  même `id` — une autre image avec la même racine, ou un identifiant de la page qui commence par le préfixe — les
  recevrait aussi. `class` reste tel qu'écrit sous toute politique : les règles de la page pour une classe
  s'appliquent à une image écrite dans la page qui l'utilise.
- **Ce que montre l'image.** Une image assainie est sûre, pas véridique : elle peut encore ressembler à un bouton, à un
  formulaire ou à un logo.
