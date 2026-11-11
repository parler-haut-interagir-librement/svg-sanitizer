<?php

declare(strict_types=1);

/*
 * The sanitizer of an SVG image on inputs nobody wrote: whatever it reads, it returns a sanitized image or a refusal
 * and never throws.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\SvgSanitizer\SvgSanitizer;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$sanitizer = new SvgSanitizer();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($sanitizer): void {
    $sanitizer->sanitize($input);
});
$config->setMaxLen(4096);
