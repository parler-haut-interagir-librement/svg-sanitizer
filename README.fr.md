<!-- phil-dev source-sha256=59f390a37b5513843eeef1a40b56e5edc92a3c353cee5177e77135ba1170d7ed -->
# phil/svg-sanitizer

**Laissez les gens téléverser et coller des images SVG sans leur permettre d'exécuter du code, de charger ce qu'ils ne
devraient pas ou d'intégrer un autre document.**

phil/svg-sanitizer lit une image SVG comme du XML — sans réseau, sans type de document, en refusant les déclarations
d'entités — et la réécrit à neuf, élément par élément et attribut par attribut, à partir de listes d'autorisation. Les
scripts, `foreignObject`, les cadres et les autres éléments qui exécutent du code ou intègrent un document sont omis
avec leur contenu. Les gestionnaires d'événements sont retirés. Chaque `href` doit pointer vers un fragment de l'image
elle-même, être une image matricielle dans une adresse `data:` sur `<image>`, ou un lien d'un schéma permis sur `<a>`.
Les attributs `style`, les éléments `<style>` qu'une politique permet et les attributs de présentation qui contiennent
plus que des noms, des nombres et des couleurs passent par `phil/css-sanitizer`. Des limites de taille, de profondeur,
de nombre d'éléments et d'attributs par élément refusent une image en entier avant qu'elle ne coûte quoi que ce soit.
Ce qui est retiré est énuméré, les cent premiers retraits avec leur raison et leur ligne, pour que l'auteur puisse le
lire.

Il s'adresse à toute application PHP qui accepte des images SVG venant de personnes : une importation de documents de
traitement de texte, une médiathèque, le téléversement d'un avatar ou d'un logo, un éditeur par blocs dont les auteurs
collent des dessins. La sortie peut être servie sans risque comme fichier `.svg` et, sous la politique par défaut —
qui laisse `<style>` de côté et préfixe chaque `id` par `svg-` —, écrite directement dans une page que d'autres
personnes lisent.

C'est une simple bibliothèque PHP, écrite de zéro, au-dessus des extensions `dom` et `libxml` de PHP, de
`ext-mbstring` et de `phil/css-sanitizer`. Rien n'est filtré par expression régulière sur du texte : l'image est
analysée, et la sortie est construite à partir d'un document vide, pour que ce que la politique ne nomme pas ne
l'atteigne jamais.

La conception est sobre : `SvgSanitizer` assainit selon une `Policy` et retourne un résultat `Sanitized` — le SVG et
les `Removal`, chacun avec sa `RemovalReason`. Quand `symfony/validator` est installé, `Validator\SafeSvg` transforme
les retraits en violations.

## État

| Partie | État |
|---|---|
| XML lu sans réseau, sans type de document ni entités ; instructions de traitement et commentaires omis | Implémenté et testé |
| Listes d'autorisation d'éléments et d'attributs, noms SVG et XLink tels qu'écrits ; éléments d'autres espaces de noms omis | Implémenté et testé |
| Éléments qui exécutent du code ou intègrent un document, gestionnaires d'événements et `xml:base` jamais permis | Implémenté et testé |
| Références : fragments, images matricielles dans des adresses `data:`, liens de schémas permis ; ruses de schéma détectées | Implémenté et testé |
| Attributs `style`, éléments `<style>` et attributs de présentation par `phil/css-sanitizer` | Implémenté et testé |
| Sûr dans la page par défaut : `<style>` laissé de côté, chaque `id` et les fragments qui pointent vers lui préfixés | Implémenté et testé |
| `<style>` conservé par `withStyleElements(true)`, chaque règle tenue sous la racine de l'image | Implémenté et testé |
| Animation SMIL soumise à la politique, jamais d'une référence, d'un style ou d'un gestionnaire d'événements | Implémenté et testé |
| Limites de taille, de profondeur, de nombre d'éléments et d'attributs par élément ; les 100 premiers retraits signalés avec leur raison et leur ligne, les autres comptés | Implémenté et testé |
| Contrainte de validateur Symfony `SafeSvg` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Assainir](doc/fr/sanitizing.md) | Assainir une image, le résultat, les retraits et leurs raisons. |
| [Politique](doc/fr/policy.md) | Ce que permet la politique par défaut, chaque `with…()`, et les limites. |
| [Références](doc/fr/references.md) | `href`, `url()`, les liens et les adresses `data:`. |
| [Menaces](doc/fr/threats.md) | Ce contre quoi l'assainisseur protège, comment, et ce qui reste à l'application. |
| [Validateur](doc/fr/validator.md) | La contrainte `SafeSvg`. |
| [Points d'extension](doc/fr/extension-points.md) | La politique, la politique CSS et son réécriveur, le résultat. |
| [Exceptions](doc/fr/exceptions.md) | `SvgSanitizerException` et `InvalidPolicy`. |
| [Tests](doc/fr/testing.md) | Les tests et le seuil de mutation de la bibliothèque, et comment tester du code qui assainit du SVG. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/svg-sanitizer
```

```php
use Phil\SvgSanitizer\SvgSanitizer;

$sanitized = new SvgSanitizer()->sanitize(<<<'SVG'
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">
      <script>alert(2)</script>
      <use href="https://example.com/sprite.svg#icon"/>
      <rect width="10" height="10" fill="url(#paper)" style="fill: red; position: fixed"/>
    </svg>
    SVG);

echo $sanitized->svg;
// <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
//
//   <use/>
//   <rect width="10" height="10" fill="url(#svg-paper)" style="fill: red"/>
// </svg>

foreach ($sanitized->removals as $removal) {
    printf("line %d: %s (%s)\n", $removal->line, $removal->reason->describe(), $removal->subject);
}
// line 1: an event handler (onload)
// line 2: an element that runs code or embeds another document (script)
// line 3: a reference to something outside the image (https://example.com/sprite.svg#icon)
// line 4: CSS that is not allowed (style: position)
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions `dom`, `libxml` et `mbstring` ; `phil/css-sanitizer` ;
`symfony/validator` pour la contrainte `SafeSvg`.

## Contribuer

Les contributions sont les bienvenues dans le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
