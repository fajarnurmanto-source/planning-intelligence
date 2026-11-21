<?php

declare(strict_types=1);

/**
 * R89 isolated QA: consistent, read-only SQLite backup from R88 QA.
 *
 * Never runs migrations; never writes source; never overwrites destination.
 * Usage: php scripts/pi-r89-backup-sqlite.php SOURCE DESTINATION
 */

if (PHP_SAPI !== 'cli' || $argc !== 3 || ! class_exists(SQLite3::class)) {
    fwrite(STDERR, "R89_SQLITE_BACKUP=INVALID_INVOCATION_OR_EXTENSION\n");
    exit(2);
}

$source = $argv[1];
$target = $argv[2];

if (! is_file($source) || file_exists($target) || is_link($target)) {
    fwrite(STDERR, "R89_SQLITE_BACKUP=SOURCE_MISSING_OR_TARGET_EXISTS\n");
    exit(3);
}

$sourceReal = realpath($source);
$targetDirReal = realpath(dirname($target));
if ($sourceReal === false || $targetDirReal === false) {
    fwrite(STDERR, "R89_SQLITE_BACKUP=INVALID_PATH\n");
    exit(4);
}
$targetRealCandidate = $targetDirReal.DIRECTORY_SEPARATOR.basename($target);
if (strcasecmp($sourceReal, $targetRealCandidate) === 0) {
    fwrite(STDERR, "R89_SQLITE_BACKUP=SOURCE_TARGET_IDENTICAL\n");
    exit(5);
}

$input = null;
$output = null;

try {
    $input = new SQLite3($sourceReal, SQLITE3_OPEN_READONLY);
    $output = new SQLite3($targetRealCandidate, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
    $input->busyTimeout(6000);
    $output->busyTimeout(6000);

    if (! $input->backup($output)) {
        throw new RuntimeException('SQLITE_BACKUP_FAILED');
    }

    $integrity = $output->querySingle('PRAGMA integrity_check');
    if ($integrity !== 'ok') {
        throw new RuntimeException('SQLITE_INTEGRITY_CHECK_FAILED');
    }

    echo "R89_QA_SQLITE_BACKUP=PASS\n";
    echo "R89_QA_SQLITE_INTEGRITY=ok\n";
    echo "R89_SOURCE_SQLITE=READ_ONLY\n";
} catch (Throwable $e) {
    fwrite(STDERR, "R89_SQLITE_BACKUP=BLOCKED\n");
    exit(6);
} finally {
    if ($output instanceof SQLite3) {
        $output->close();
    }
    if ($input instanceof SQLite3) {
        $input->close();
    }
}
