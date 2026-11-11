<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

/**
 * The library's declared dependencies, checked against the library installed on its own.
 *
 * `symfony/validator` is used by the production code under `src/Validator` but only required in development and
 * suggested: the `SafeSvg` constraint is there for applications that have the validator, and the rest of the library
 * never loads it.
 */
return new Configuration()
    ->addPathToScan(__DIR__.'/src', isDev: false)
    ->addPathToScan(__DIR__.'/tests', isDev: true)
    ->ignoreErrorsOnPackages(['symfony/validator'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
