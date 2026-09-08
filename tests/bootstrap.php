<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

$root = dirname(__DIR__);
$loader = require $root.'/vendor/autoload.php';

/*
 * The suite has to be testing the checkout it was started from.
 *
 * Composer works out where the code lives from the autoloader's own `__FILE__`,
 * and PHP resolves that through symlinks. So a `vendor/` symlinked from another
 * checkout - the obvious shortcut when setting up a git worktree - quietly
 * loads that other checkout's `src/`. The suite then runs green while testing
 * code nobody changed, which is the worst answer a test run can give: a wrong
 * one that looks right.
 *
 * Checked here rather than left to be noticed, because it cannot be noticed.
 * It was found by accident, from an assertion that should have passed and did
 * not.
 */
$psr4 = $loader->getPrefixesPsr4();
$source = realpath($psr4['Aurora\\'][0] ?? '');
$expected = realpath($root);

if (false === $source || false === $expected || !str_starts_with($source, $expected.DIRECTORY_SEPARATOR)) {
    fwrite(STDERR, sprintf(
        "The autoloader points outside this checkout, so the suite would test the wrong code.\n"
            ."  running from: %s\n"
            ."  loading from: %s\n"
            ."A symlinked vendor/ does this. Give the worktree a real copy.\n",
        $expected ?: $root,
        $source ?: 'nowhere - Aurora\\ is not registered',
    ));

    exit(1);
}

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv($root.'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}
