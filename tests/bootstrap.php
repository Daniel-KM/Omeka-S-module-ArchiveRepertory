<?php declare(strict_types=1);

/**
 * Bootstrap file for module tests.
 *
 * Use Common module Bootstrap helper for test setup.
 */

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        'ArchiveRepertory',
    ],
    'ArchiveRepertoryTest',
    __DIR__ . '/ArchiveRepertoryTest'
);

// Silence third-party deprecations (e.g. laminas-session "Constant SID is
// deprecated") that would otherwise be printed during tests and mark them risky
// under beStrictAboutOutputDuringTests.
error_reporting(E_ALL & ~E_DEPRECATED);
