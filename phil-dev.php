<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The constraint sits beside its validator, as Symfony pairs a Constraint and its ConstraintValidator and as
            // phil/css-sanitizer places SafeCss: an Attribute namespace would split the pair.
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Validator/SafeSvg.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // `preg_replace()` and `preg_replace_callback()` return null only on a regular expression error, which a
            // constant expression on a string of valid UTF-8 never raises: the cast only tells PHPStan so.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$compact = \\(string\\) preg_replace\\(.*',
                    'return \\(string\\) preg_replace_callback\\(self::URL_FRAGMENT.*',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The sanitizer reads and writes images through the DOM extension of PHP, which is outside the default, and
        // gives their CSS to phil/css-sanitizer; only its constraint, under Validator, sees symfony/validator.
        'layers' => [
            'PhilSvgSanitizer' => '^Phil\\\\SvgSanitizer\\\\(?!Validator\\\\).*',
            'PhilSvgSanitizerValidator' => '^Phil\\\\SvgSanitizer\\\\Validator\\\\.*',
        ],
        'ruleset' => [
            'PhilSvgSanitizer' => ['DefaultAllowed', 'PhilCssSanitizer', 'Forbidden'],
            'PhilSvgSanitizerValidator' => ['PhilSvgSanitizer', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // Each parser on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'svg-sanitizer' => 'tests/Fuzz/svg-sanitizer.php',
        ],
    ],
];
