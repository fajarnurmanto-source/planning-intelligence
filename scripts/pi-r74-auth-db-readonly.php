<?php
/**
 * R74 read-only account inventory. No Laravel boot, password output, writes,
 * migrations, HTTP login or environment mutation.
 * php pi-r74-auth-db-readonly.php <sqlite-path> <username>
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "PI_R74_DB_PROBE_BAD_ARGS\n");
    exit(2);
}
if (!class_exists(SQLite3::class)) {
    fwrite(STDERR, "PI_R74_PHP_SQLITE3_UNAVAILABLE\n");
    exit(2);
}
$path = $argv[1];
$username = $argv[2];
if (!is_file($path)) {
    fwrite(STDERR, "PI_R74_EXPECTED_SQLITE_MISSING\n");
    exit(2);
}
try {
    $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
    $db->busyTimeout(1500);
    try {
        $integrity = $db->querySingle('PRAGMA quick_check');
        if ($integrity !== 'ok') {
            throw new RuntimeException('PI_R74_SQLITE_QUICK_CHECK_FAILED');
        }
        $stmt = $db->prepare('SELECT username, tenant_id, role, password FROM users WHERE LOWER(username)=LOWER(:u)');
        if ($stmt === false) {
            throw new RuntimeException('PI_R74_USER_QUERY_UNAVAILABLE');
        }
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $res = $stmt->execute();
        if ($res === false) {
            throw new RuntimeException('PI_R74_USER_QUERY_FAILED');
        }
        $count = 0;
        $roles = [];
        $tenants = [];
        $schemes = [];
        while (($row = $res->fetchArray(SQLITE3_ASSOC)) !== false) {
            $count++;
            $roles[] = $row['role'] ?? '';
            $tenants[] = $row['tenant_id'] ?? '';
            $info = password_get_info((string)($row['password'] ?? ''));
            $schemes[] = ($info['algoName'] ?? 'unknown');
        }
        $res->finalize();
        echo "PI_R74_SQLITE_QUICK_CHECK=ok\n";
        echo "PI_R74_MATCHING_USERNAME_COUNT={$count}\n";
        // Do not reveal password hashes, even prefixes, or credentials.
        if ($count === 1) {
            echo "PI_R74_ACCOUNT_TENANT_EXPECTED=" . ($tenants[0] === 'ID-DIY-YOG' ? 'YES' : 'NO') . "\n";
            echo "PI_R74_ACCOUNT_ROLE_EXPECTED=" . ($roles[0] === 'institution_admin' ? 'YES' : 'NO') . "\n";
            echo "PI_R74_ACCOUNT_PASSWORD_SCHEME=" . $schemes[0] . "\n";
        } else {
            echo "PI_R74_ACCOUNT_IDENTITY_REVIEW_REQUIRED=YES\n";
        }
        echo "PI_R74_DB_PROBE_READ_ONLY=YES\n";
    } finally {
        $db->close();
    }
} catch (Throwable $e) {
    fwrite(STDERR, "PI_R74_DB_READ_ONLY_ERROR=" . preg_replace('/[^A-Za-z0-9_]/', '', $e->getMessage()) . "\n");
    exit(2);
}
