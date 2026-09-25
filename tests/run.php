<?php

declare(strict_types=1);

/**
 * Run the test suite.
 *
 *   php tests/run.php            everything
 *   php tests/run.php money      only files whose name matches "money"
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/bootstrap.php';

$filter = $argv[1] ?? '';
$files = glob(__DIR__ . '/*_test.php') ?: [];
sort($files);

if ($filter !== '') {
    $files = array_values(array_filter($files, static fn (string $f): bool => str_contains(basename($f), $filter)));
}

if ($files === []) {
    fwrite(STDERR, "No test files matched.\n");
    exit(1);
}

/**
 * Collect output instead of printing it.
 *
 * PHP refuses to start or rotate a session once output has been sent, so a
 * runner that prints as it goes cannot exercise the real sign-in path. Holding
 * everything until the end keeps the production code strict.
 */
$report = '';
$out = static function (string $line) use (&$report): void {
    $report .= $line;
};
// If code under test calls exit(), still show what ran up to that point.
$flushed = false;
register_shutdown_function(static function () use (&$report, &$flushed): void {
    if (!$flushed) {
        echo $report, "\n\033[31mThe run stopped early: something under test called exit().\033[0m\n";
    }
});

$name = test_db_reset();
$out("Test database: {$name}\n\n");

$failures = [];
$passed = 0;
$started = microtime(true);

foreach ($files as $file) {
    $GLOBALS['sanatec_tests'] = [];
    require $file;

    $out(basename($file, '_test.php') . "\n");

    foreach ($GLOBALS['sanatec_tests'] as $case) {
        try {
            ($case['body'])();
            $out("  \033[32m✓\033[0m " . $case['name'] . "\n");
            $passed++;
        } catch (Throwable $e) {
            $out("  \033[31m✗\033[0m " . $case['name'] . "\n");
            $failures[] = ['file' => basename($file), 'name' => $case['name'], 'error' => $e];
        }
    }
    $out("\n");
}

$elapsed = round((microtime(true) - $started) * 1000);

foreach ($failures as $f) {
    $out("\033[31mFAILED\033[0m {$f['file']} — {$f['name']}\n");
    $out("      " . str_replace("\n", "\n      ", $f['error']->getMessage()) . "\n");
    if (!$f['error'] instanceof TestFailure) {
        $out("      " . $f['error']->getFile() . ':' . $f['error']->getLine() . "\n");
    }
    $out("\n");
}

$out(sprintf(
    "%d passed, %d failed, %d assertions, %dms\n",
    $passed,
    count($failures),
    $GLOBALS['sanatec_assertions'],
    $elapsed
));

$flushed = true;
echo $report;

exit($failures === [] ? 0 : 1);
