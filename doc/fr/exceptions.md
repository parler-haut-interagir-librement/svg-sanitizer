<!-- phil-dev source-sha256=cbe27e4c943d9d572090fe0bfb2011fd841a8ef290616168621a20653fa2a116 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\SvgSanitizer\Exception\SvgSanitizerException`.

| Exception | Quand |
|---|---|
| `InvalidPolicy` | On demande à une politique de permettre un élément, un attribut ou un schéma de lien qui n'est jamais permis, ou on lui donne une limite inférieure à 1 |

L'assainissement ne lève jamais d'exception : une image illisible, trop grande ou qui déclare des entités est refusée
en entier, et le retrait en donne la raison.
