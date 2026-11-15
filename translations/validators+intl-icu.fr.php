<?php

declare(strict_types=1);

/*
 * The messages of the SafeSvg constraint in French, in ICU MessageFormat: what was removed follows the reason, and
 * the plural follows the count. The keys are the default messages of the constraint.
 */
return [
    'This SVG image holds {{ removed }} on line {{ line }}: {{ subject }}.' => 'Cette image SVG contient {reason, select, element {un élément qui n’est pas permis} denied_element {un élément qui exécute du code ou intègre un autre document} attribute {un attribut qui n’est pas permis} event_handler {un gestionnaire d’événement} external_reference {une référence à quelque chose hors de l’image} dangerous_url {une adresse qui exécute du code} link {un lien qui n’est pas permis} animation {une animation qui n’est pas permise} style {du CSS qui n’est pas permis} processing_instruction {une instruction de traitement} entity_declaration {une image qui déclare des entités} malformed {une image qui n’est pas du XML bien formé} not_svg {un document qui n’est pas une image SVG} too_large {une image trop grande} too_deep {des éléments imbriqués trop profondément} too_many_elements {une image qui a trop d’éléments} too_many_attributes {un élément qui a trop d’attributs} other {{removed}}} à la ligne {line} : {subject}.',
    'This SVG image is refused: {{ removed }}.' => 'Cette image SVG est refusée : {reason, select, element {un élément qui n’est pas permis} denied_element {un élément qui exécute du code ou intègre un autre document} attribute {un attribut qui n’est pas permis} event_handler {un gestionnaire d’événement} external_reference {une référence à quelque chose hors de l’image} dangerous_url {une adresse qui exécute du code} link {un lien qui n’est pas permis} animation {une animation qui n’est pas permise} style {du CSS qui n’est pas permis} processing_instruction {une instruction de traitement} entity_declaration {une image qui déclare des entités} malformed {une image qui n’est pas du XML bien formé} not_svg {un document qui n’est pas une image SVG} too_large {une image trop grande} too_deep {des éléments imbriqués trop profondément} too_many_elements {une image qui a trop d’éléments} too_many_attributes {un élément qui a trop d’attributs} other {{removed}}}.',
    'This SVG image holds {{ count }} more parts that are not allowed.' => '{count, plural, one {Cette image SVG contient # autre partie qui n’est pas permise.} other {Cette image SVG contient # autres parties qui ne sont pas permises.}}',
];
