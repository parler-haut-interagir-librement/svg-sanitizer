<?php

declare(strict_types=1);

/*
 * The message of the SafeSvg constraint that counts, in ICU MessageFormat: the plural follows the count. The key is
 * the default message of the constraint.
 */
return [
    'This SVG image holds {{ count }} more parts that are not allowed.' => '{count, plural, one {This SVG image holds # more part that is not allowed.} other {This SVG image holds # more parts that are not allowed.}}',
];
