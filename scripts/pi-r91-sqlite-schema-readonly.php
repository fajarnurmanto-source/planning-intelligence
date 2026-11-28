<?php
/**
 * PI R91: metadata-only SQLite gate for the original R74 Windows app.
 * Opens the exact original main DB READ ONLY and reads ONLY metadata:
 * table/column names, SQLite quick_check, expected Laravel migration ledger.
 * It NEVER selects user records, passwords, review decisions, sessions,
 * tokens, documents, evidence or inference settings. Never modifies SQL.
 *
 * php scripts/pi-r91-sqlite-schema-readonly.php
 * CI-only: php ... --ci-db <path inside RUNNER_TEMP>
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !extension_loaded('sqlite3')) {
    fwrite(STDERR, "PI_R91_SQLITE3_CLI_EXTENSION_REQUIRED\n");
    exit(2);
}
$root = 'D:\Research\PlanningIntelligence\planning-intelligence-v0.0-starter\planning-intelligence-v0.0\laravel';
$mainPath = $root . '\database\database.sqlite';
$dbPath = $mainPath;
$fixture = false;
if ($argc === 3 && $argv[1] === '--ci-db') {
    if (getenv('GITHUB_ACTIONS') !== 'true') {
        fwrite(STDERR, "PI_R91_CI_DATABASE_NOT_AUTHORIZED\n");
        exit(2);
    }
    $runnerTemp = getenv('RUNNER_TEMP');
    $resolvedRunner = is_string($runnerTemp) ? realpath($runnerTemp) : false;
    $resolvedDb = realpath($argv[2]);
    if ($resolvedRunner === false || $resolvedDb === false) {
        fwrite(STDERR, "PI_R91_CI_DATABASE_NOT_FOUND\n");
        exit(2);
    }
    $prefix = rtrim(str_replace('/', '\\', $resolvedRunner), '\\') . '\\';
    $lowerPath = strtolower(str_replace('/', '\\', $resolvedDb));
    if (!str_starts_with($lowerPath, strtolower($prefix))) {
        fwrite(STDERR, "PI_R91_CI_DATABASE_OUTSIDE_RUNNER_TEMP\n");
        exit(2);
    }
    $dbPath = $resolvedDb;
    $fixture = true;
} elseif ($argc !== 1) {
    fwrite(STDERR, "PI_R91_UNSUPPORTED_ARGUMENTS\n");
    exit(2);
}
$resolved = realpath($dbPath);
if ($resolved === false || !is_file($resolved)) {
    fwrite(STDERR, "PI_R91_ORIGINAL_DATABASE_MISSING\n");
    exit(2);
}
if (!$fixture) {
    if (strtolower(str_replace('/', '\\', $resolved))
        !== strtolower(str_replace('/', '\\', $mainPath))) {
        fwrite(STDERR, "PI_R91_MAIN_DATABASE_PATH_MISMATCH\n");
        exit(2);
    }
}
echo "PI_R91_SQLITE_AUDIT=METADATA_READ_ONLY\n";
echo 'PI_R91_DATABASE_TARGET=' . ($fixture ? 'CI_TEMP_FIXTURE' : 'PROTECTED_ORIGINAL') . "\n";
$expectedColumns = [
    'migrations' => ['migration', 'batch'],
    'users' => ['id', 'username', 'tenant_id', 'role', 'password'],
    'sessions' => ['id', 'user_id', 'payload', 'last_activity'],
    'cache' => ['key', 'value', 'expiration'],
    'cache_locks' => ['key', 'owner', 'expiration'],
    'jobs' => ['id', 'queue', 'payload', 'attempts'],
    'job_batches' => ['id', 'name', 'total_jobs'],
    'failed_jobs' => ['id', 'uuid', 'exception'],
    'regulatory_evidence_reviews' => [
        'id', 'tenant_id', 'candidate_id', 'review_dimension', 'decision',
        'reason', 'source_manifest_sha256', 'candidate_sha256',
        'reviewer_id', 'decided_at', 'created_at', 'updated_at',
    ],
];
$expectedMigrations = [
    '0001_01_01_000000_create_users_table',
    '0001_01_01_000001_create_cache_table',
    '0001_01_01_000002_create_jobs_table',
    '2026_11_07_033435_add_pi_identity_fields_to_users_table',
    '2026_11_15_150000_create_regulatory_evidence_reviews_table',
];
$completeTables = 0;
$missingColumns = 0;
$applied = 0;
$unapplied = 0;
try {
    $db = new SQLite3($resolved, SQLITE3_OPEN_READONLY);
    $db->enableExceptions(true);
    $db->busyTimeout(1200);
    try {
        $quickCheck = (string)$db->querySingle('PRAGMA quick_check');
        echo 'PI_R91_SQLITE_QUICK_CHECK=' . ($quickCheck === 'ok' ? 'ok' : 'NOT_OK') . "\n";
        if ($quickCheck !== 'ok') {
            throw new RuntimeException('PI_R91_SQLITE_INTEGRITY_HOLD');
        }
        foreach ($expectedColumns as $table => $columns) {
            // All table identifiers come from the above constant allowlist.
            $list = $db->query("PRAGMA table_info('" . $table . "')");
            $found = [];
            while (($info = $list->fetchArray(SQLITE3_ASSOC)) !== false) {
                $found[(string)$info['name']] = true;
            }
            $list->finalize();
            if ($found === []) {
                echo 'PI_R91_SCHEMA_TABLE=' . $table . ':MISSING' . "\n";
                $missingColumns += count($columns);
                continue;
            }
            $missing = array_values(array_filter($columns,
                static fn(string $name): bool => !isset($found[$name])));
            if ($missing === []) {
                echo 'PI_R91_SCHEMA_TABLE=' . $table . ':REQUIRED_COLUMNS_PRESENT' . "\n";
                $completeTables++;
            } else {
                echo 'PI_R91_SCHEMA_TABLE=' . $table . ':MISSING_REQUIRED_COLUMNS' . "\n";
                foreach ($missing as $name) {
                    echo 'PI_R91_SCHEMA_MISSING_COLUMN=' . $table . '.' . $name . "\n";
                }
                $missingColumns += count($missing);
            }
        }
        if ($completeTables < count($expectedColumns)) {
            echo "PI_R91_APPLIED_MIGRATION_LEDGER=NOT_PROVEN_INCOMPLETE_SCHEMA\n";
        } else {
            foreach ($expectedMigrations as $migration) {
                $query = $db->prepare('SELECT 1 FROM migrations WHERE migration = :name LIMIT 1');
                $query->bindValue(':name', $migration, SQLITE3_TEXT);
                $row = $query->execute();
                $wasApplied = $row->fetchArray(SQLITE3_NUM) !== false;
                $row->finalize();
                echo 'PI_R91_EXPECTED_MIGRATION=' . $migration . ':' .
                    ($wasApplied ? 'APPLIED' : 'NOT_IN_LEDGER') . "\n";
                $wasApplied ? $applied++ : $unapplied++;
            }
            echo 'PI_R91_EXPECTED_MIGRATIONS_APPLIED=' . $applied . "\n";
            echo 'PI_R91_EXPECTED_MIGRATIONS_NOT_IN_LEDGER=' . $unapplied . "\n";
        }
    } finally {
        $db->close();
    }
} catch (Throwable $e) {
    // Only public, constant error codes are printed; never SQL or values.
    fwrite(STDERR, "PI_R91_READ_ONLY_METADATA_QUERY_FAILED\n");
    exit(2);
}
echo 'PI_R91_REQUIRED_TABLES_PRESENT=' . $completeTables . '/' . count($expectedColumns) . "\n";
echo 'PI_R91_REQUIRED_COLUMNS_MISSING=' . $missingColumns . "\n";
echo 'PI_R91_SCHEMA_MINIMUM=' .
    (($missingColumns === 0 && $unapplied === 0 && $applied === count($expectedMigrations))
        ? 'REQUIRED_COLUMNS_AND_LEDGER_PASS' : 'NOT_YET_VERIFIED') . "\n";
echo "PI_R91_AUTH_DATA_PASSWORD_SESSION_VALUES=NOT_READ\n";
echo "PI_R91_SCHEMA_EQUIVALENCE_BEYOND_MINIMUM=NOT_PROVEN\n";
echo "PI_R91_PROTECTED_DATABASE_MUTATIONS=ZERO\n";
echo "PI_R91_SQLITE_METADATA_AUDIT=COMPLETE_READ_ONLY\n";
