<?php

declare(strict_types=1);

use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\FinderConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Unit',
            // tests/Integration needs a running Redis (the Redis tests skip
            // via REDIS_HOST); keep it out of the default Unit suite so
            // `composer build` stays green with no Redis reachable and the
            // integration test classes are not executed twice (which breaks
            // infection's coverage mapping when they carry #[Covers]).
            location: new FinderConfig(include: ['tests'], exclude: ['tests/Integration']),
        ),
        new SuiteConfig(
            name: 'Benchmarks',
            location: ['benchmarks'],
        ),
        new SuiteConfig(
            name: 'Integration',
            location: ['tests/Integration'],
        ),
    ],
);
