<?php

/**
 * Line, branch and path coverage for the whole suite, working around
 * Xdebug 3.4.x intermittently segfaulting under --path-coverage on long
 * runs: each test directory is covered in its own process (retried when
 * the process crashes) and the partial reports are merged.
 *
 * Usage: composer test-coverage-branches [-- --clover clover.xml] [-- --html build/coverage]
 */

declare(strict_types=1);

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;
use SebastianBergmann\CodeCoverage\Report\Html\Facade as HtmlReport;
use SebastianBergmann\CodeCoverage\Report\Text;
use SebastianBergmann\CodeCoverage\Report\Thresholds;

require __DIR__ . '/../vendor/autoload.php';

const MAX_ATTEMPTS  = 8;
/** Shell-style (128 + signal) and raw signal numbers for SIGABRT and SIGSEGV. */
const CRASH_CODES   = [6, 11, 134, 139];
const PROJECT_ROOT  = __DIR__ . '/..';

$options = getopt('', ['clover:', 'html:']);
$parts   = array_merge(
    [PROJECT_ROOT . '/tests/Unit'],
    glob(PROJECT_ROOT . '/tests/Integration/*', GLOB_ONLYDIR) ?: [],
    glob(PROJECT_ROOT . '/tests/Integration/*Test.php') ?: [],
);

$workDir = sys_get_temp_dir() . '/contenir-db-model-coverage-' . getmypid();
if (! is_dir($workDir) && ! mkdir($workDir, 0o700, true)) {
    fwrite(STDERR, "Cannot create {$workDir}\n");
    exit(1);
}

$merged = null;
foreach ($parts as $index => $part) {
    $target = "{$workDir}/part-{$index}.cov";
    $label  = substr($part, strlen(PROJECT_ROOT) + 1);
    $exit   = runPart($part, $target, $label);
    if (0 !== $exit) {
        fwrite(STDERR, "{$label}: PHPUnit exited with {$exit}; fix the failing tests first\n");
        exit($exit);
    }

    /** @var CodeCoverage $coverage */
    $coverage = require $target;
    unlink($target);
    if (null === $merged) {
        $merged = $coverage;
    } else {
        $merged->merge($coverage);
    }
}

rmdir($workDir);
if (null === $merged) {
    fwrite(STDERR, "No test directories found\n");
    exit(1);
}

echo (new Text(Thresholds::default(), showUncoveredFiles: true, showOnlySummary: false))->process($merged, showColors: true);

if (isset($options['clover']) && is_string($options['clover'])) {
    (new Clover())->process($merged, $options['clover']);
    echo "Clover report written to {$options['clover']}\n";
}

if (isset($options['html']) && is_string($options['html'])) {
    (new HtmlReport())->process($merged, $options['html']);
    echo "HTML report written to {$options['html']}\n";
}

/**
 * Run one test directory with path coverage, retrying when Xdebug crashes
 * the process. Returns PHPUnit's exit code from the last attempt.
 */
function runPart(string $part, string $target, string $label): int
{
    $command = [
        PHP_BINARY,
        PROJECT_ROOT . '/vendor/bin/phpunit',
        '--path-coverage',
        '--coverage-php',
        $target,
        '--no-output',
        $part,
    ];

    for ($attempt = 1; $attempt <= MAX_ATTEMPTS; $attempt++) {
        $process = proc_open($command, [1 => STDOUT, 2 => STDERR], $pipes, PROJECT_ROOT, ['XDEBUG_MODE' => 'coverage'] + getenv());
        $exit    = is_resource($process) ? proc_close($process) : 1;
        if (! in_array($exit, CRASH_CODES, true)) {
            $retries = $attempt > 1 ? " (after {$attempt} attempts)" : '';
            fwrite(STDERR, sprintf("%-46s %s%s\n", $label, 0 === $exit ? 'ok' : "exit {$exit}", $retries));

            return $exit;
        }
    }

    fwrite(STDERR, "{$label}: still crashing after " . MAX_ATTEMPTS . " attempts\n");

    return 139;
}
