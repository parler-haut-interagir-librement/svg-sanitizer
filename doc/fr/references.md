<!-- phil-dev source-sha256=eb6d28fdbe5ab8262ede0ec0f134745ee92ce4cde58a0df520fa9f2781ccd7b8 -->
# Références

Une image SVG peut pointer hors d'elle-même de deux façons : un `href` (ou l'ancien `xlink:href`), et un `url()` dans
le CSS ou dans un attribut de présentation. L'assainisseur ne conserve que ce qui pointe dans l'image, ainsi que les
deux exceptions qu'une politique permet.

## `href` et `xlink:href`

| Valeur | Conservée |
|---|---|
| `#paper`, ` #paper ` | Toujours : un fragment de l'image, sans les espaces autour, écrit après le préfixe des identifiants — `#svg-paper` par défaut |
| `data:image/png;base64,…` sur `<image>` ou `<feImage>` | Quand la politique permet les images matricielles dans des adresses `data:` : PNG, JPEG, GIF, WebP et AVIF |
| `https://example.com/` sur `<a>` | Quand la politique permet le schéma |
| Tout le reste | Jamais |

Ce qui n'est pas conservé est retiré avec la raison qui convient : `DangerousUrl` quand le schéma exécute du code ou
contient un document (`javascript:`, `vbscript:`, `data:` hors d'une image matricielle — dont un SVG dans une adresse
`data:`), `Link` pour un autre lien de `<a>`, `ExternalReference` pour le reste : un autre fichier, un autre site, un
chemin relatif.

Les navigateurs ignorent les espaces et les caractères de contrôle à l'intérieur d'un schéma, si bien que
`java&#9;script:` s'exécute ; l'assainisseur les ignore aussi avant de lire le schéma. La valeur réécrite est celle de
la source, sans les espaces autour.

## `url()`

`url()` est lu par `phil/css-sanitizer` partout où se trouve du CSS : dans les attributs `style`, dans les éléments
`<style>`, et dans les attributs de présentation qui contiennent plus que des noms, des nombres et des couleurs —
`fill="url(#paper)"`, `mask="url(#fade)"`, `filter="url(#blur)"`. Un fragment est conservé, écrit après le préfixe
des identifiants comme le sont les identifiants ; toute autre URL va au réécriveur de la politique CSS, qui les refuse toutes par défaut. Les échappements sont résolus avant que la fonction
ne soit lue : `u\72l(…)` vaut `url(…)`.

## Ce que décide la page du lecteur

Une image servie comme fichier et affichée par `<img>` ne peut ni exécuter un script ni suivre un lien, quoi qu'elle
contienne ; une image écrite directement dans une page, ou ouverte seule, le peut. L'assainisseur rend les deux cas
sûrs — la politique par défaut empêche une image dans la page de styler la page ou de nommer ses variables, comme
l'explique [Politique](policy.md#images-dans-la-page) — ; la `Content-Security-Policy` de la page et la `Content-Disposition` du fichier restent l'affaire de
l'application — voir [Menaces](threats.md).
