<?php
declare(strict_types=1);

/**
 * APR platform preflight. Reads only Composer lock metadata and loaded PHP
 * extensions; no Laravel boot, .env, database, dependency installation or egress.
 * Presence only: Composer remains authoritative for version constraints.
 */
if (PHP_SAPI !== 'cli' || $argc !== 3 || !in_array($argv[2], ['runtime', 'ci'], true)) {
    fwrite(STDERR, "PI_APR_PLATFORM_ARGUMENT_HOLD\n");
    exit(2);
}
try {
    $path = $argv[1];
    if (!is_file($path) || is_link($path) || filesize($path) > 8388608) {
        throw new RuntimeException();
    }
    $lock = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($lock) || !is_array($lock['packages'] ?? null)
        || !is_array($lock['packages-dev'] ?? null) || !is_array($lock['platform'] ?? null)) {
        throw new RuntimeException();
    }
    $sets = [$lock['platform']];
    if ($argv[2] === 'ci') {
        $sets[] = $lock['platform-dev'] ?? [];
    }
    $packages = $argv[2] === 'ci' ? array_merge($lock['packages'], $lock['packages-dev']) : $lock['packages'];
    foreach ($packages as $package) {
        if (!is_array($package)) {
            throw new RuntimeException();
        }
        $sets[] = $package['require'] ?? [];
    }
    $required = ['sqlite3' => true, 'pdo_sqlite' => true];
    foreach ($sets as $requirements) {
        if (!is_array($requirements)) {
            throw new RuntimeException();
        }
        foreach ($requirements as $name => $constraint) {
            if (is_string($name) && str_starts_with($name, 'ext-')) {
                $extension = substr($name, 4);
                if (!preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/D', $extension)) {
                    throw new RuntimeException();
                }
                $required[strtolower($extension)] = true;
            }
        }
    }
    ksort($required);
    $missing = [];
    foreach (array_keys($required) as $extension) {
        if (!extension_loaded($extension)) {
            $missing[] = $extension;
        }
    }
    echo 'PI_APR_PLATFORM_REQUIRED_EXTENSIONS=' . count($required) . "\n";
    foreach ($missing as $extension) {
        echo 'PI_APR_PLATFORM_MISSING_EXTENSION=' . $extension . "\n";
    }
    echo 'PI_APR_PLATFORM_EXTENSION_PRESENCE=' . ($missing === [] ? 'PASS' : 'HOLD') . "\n";
    echo "PI_APR_PLATFORM_VERSION_CONSTRAINTS=NOT_ASSESSED\n";
    exit($missing === [] ? 0 : 1);
} catch (Throwable) {
    fwrite(STDERR, "PI_APR_PLATFORM_LOCK_METADATA_HOLD\n");
    exit(2);
}
