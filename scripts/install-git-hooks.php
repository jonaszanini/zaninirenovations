<?php

/**
 * Point this repo's git hooks at .githooks/ (shared, version-controlled).
 *
 *   composer hooks:install
 */

$root = dirname(__DIR__);

chdir($root);

passthru('git config core.hooksPath .githooks', $code);

if ($code !== 0) {
    fwrite(STDERR, "Failed to set git core.hooksPath — are you inside a git repo?\n");
    exit(1);
}

echo "Git hooks installed.\n";
echo "  Before each push: composer test:pre-push (Blade syntax + route smoke)\n";
echo "  Skip once:        SKIP_PRE_PUSH_CHECKS=1 git push\n";
