<?php
/**
 * Confirm one local R74 account's bcrypt against a password read from STDIN.
 * NEVER prints either the submitted password or any stored hash.
 * Does not boot Laravel, write SQLite, create a session or contact an API.
 *
 * php scripts/pi-r74-verify-entered-password.php <sqlite-file> <username>
 * Private password bytes arrive via STDIN only, never in CLI arguments.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || $argc !== 3) {
    fwrite(STDERR, "PI_R74_PASSWORD_CHECK_BAD_ARGS\n");
    exit(2);
}
if (!class_exists(SQLite3::class)) {
    fwrite(STDERR, "PI_R74_SQLITE3_NOT_AVAILABLE\n");
    exit(2);
}
$path = $argv[1];
$username = $argv[2];
if (!is_file($path)) {
    fwrite(STDERR, "PI_R74_ACTIVE_DB_NOT_FOUND\n");
    exit(2);
}
if (strlen($username) < 3 || strlen($username) > 128) {
    fwrite(STDERR, "PI_R74_ACCOUNT_SELECTOR_INVALID\n");
    exit(2);
}
$entered = stream_get_contents(STDIN, 2048);
if (!is_string($entered) || strlen($entered) > 1024) {
    fwrite(STDERR, "PI_R74_PASSWORD_STDIN_INVALID\n");
    exit(2);
}
// PowerShell pipes one native-process line ending; strip precisely one.
// PowerShell 5.1 can prepend EF BB BF to native-process STDIN.
// Strip exactly one transport BOM before comparing the real entered bytes.
$transportHadBom = str_starts_with($entered, "\xEF\xBB\xBF");
if ($transportHadBom) {
    $entered = substr($entered, 3);
}
$entered = preg_replace('/(?:\r\n|\n)\z/D', '', $entered);
if (!is_string($entered) || $entered === '') {
    fwrite(STDERR, "PI_R74_PASSWORD_STDIN_EMPTY\n");
    exit(2);
}
try {
    $db = new SQLite3($path, SQLITE3_OPEN_READONLY);
    $db->busyTimeout(1500);
    try {
        if ($db->querySingle('PRAGMA quick_check') !== 'ok') {
            throw new RuntimeException('PI_R74_DB_INTEGRITY_HOLD');
        }
        $stmt = $db->prepare(
            'SELECT password, tenant_id, role FROM users
             WHERE username = :username LIMIT 2'
        );
        if ($stmt === false) {
            throw new RuntimeException('PI_R74_ACCOUNT_QUERY_NOT_AVAILABLE');
        }
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result === false) {
            throw new RuntimeException('PI_R74_ACCOUNT_QUERY_FAILED');
        }
        $rows = [];
        while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
            $rows[] = $row;
        }
        $result->finalize();
        if (count($rows) !== 1 ||
            $rows[0]['tenant_id'] !== 'ID-DIY-YOG' ||
            $rows[0]['role'] !== 'institution_admin') {
            throw new RuntimeException('PI_R74_ACCOUNT_IDENTITY_HOLD');
        }
        $stored = (string)$rows[0]['password'];
        if (password_get_info($stored)['algoName'] !== 'bcrypt') {
            throw new RuntimeException('PI_R74_PASSWORD_HASH_SCHEME_HOLD');
        }
        // Same bcrypt verification primitive as Laravel's Hash::check.
        $matched = password_verify($entered, $stored);
        // In the 21-Nov R87 recovery, PowerShell piped the entered password
        // to PHP and stripped a newline only. If that transport supplied a
        // UTF-8 BOM, the database could contain the hash of BOM+password.
        // Detect this historical mistake without changing the account.
        $historicalBomMatch = !$matched
            && password_verify("\xEF\xBB\xBF" . $entered, $stored);
        echo 'PI_R74_ENTERED_PASSWORD_MATCHES_RECOVERED_ACCOUNT=' .
            ($matched ? 'YES' : 'NO') . "\n";
        echo 'PI_R74_R87_RECOVERY_BOM_PREFIX_MATCH=' .
            ($historicalBomMatch ? 'YES' : 'NO') . "\n";
        echo "PI_R74_CREDENTIAL_CHECK_READ_ONLY=YES\n";
    } finally {
        $db->close();
    }
} catch (Throwable $e) {
    // Only preapproved error codes are thrown; no credentials in messages.
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(2);
} finally {
    unset($entered, $stored, $rows);
}
