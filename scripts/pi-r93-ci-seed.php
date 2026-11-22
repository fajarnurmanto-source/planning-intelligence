<?php
/**
 * R93 isolated CI-only seed, never callable on a user Windows installation.
 * SQLite DB must physically reside under the current GitHub runner temp root.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli' || getenv('GITHUB_ACTIONS') !== 'true'
    || getenv('APP_ENV') !== 'testing'
    || getenv('DB_CONNECTION') !== 'sqlite') {
    fwrite(STDERR, "PI_R93_CI_SEED_ENV_DENIED\n");
    exit(2);
}

$temp = realpath((string) getenv('RUNNER_TEMP'));
$db = realpath((string) getenv('DB_DATABASE'));
if ($temp === false || $db === false
    || ! str_starts_with(str_replace('\\', '/', strtolower($db)),
        rtrim(str_replace('\\', '/', strtolower($temp)), '/') . '/')
    || ! str_ends_with(strtolower($db), '.sqlite')) {
    fwrite(STDERR, "PI_R93_CI_SEED_DATABASE_OUTSIDE_EPHEMERAL_RUNNER\n");
    exit(2);
}

$base = dirname(__DIR__) . '/laravel';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! $app->environment('testing')
    || config('database.default') !== 'sqlite'
    || realpath((string) config('database.connections.sqlite.database')) !== $db) {
    fwrite(STDERR, "PI_R93_CI_SEED_BOOTSTRAP_DATABASE_MISMATCH\n");
    exit(2);
}

if (\App\Models\User::query()->exists()) {
    fwrite(STDERR, "PI_R93_CI_SEED_EXPECTED_EMPTY_USERS\n");
    exit(2);
}

\App\Models\User::query()->create([
    'name' => 'R93 Disposable Admin',
    'username' => 'r93_ci_operator',
    'email' => 'r93-ci-admin@example.invalid',
    'password' => 'R93-CI-Only-Password-Not-A-User-Secret!',
    'tenant_id' => 'ID-DIY-YOG',
    'role' => 'institution_admin',
]);

\App\Models\User::query()->create([
    'name' => 'R93 Disposable Viewer',
    'username' => 'r93_ci_viewer',
    'email' => 'r93-ci-viewer@example.invalid',
    'password' => 'R93-CI-Viewer-Password-Not-A-Secret!',
    'tenant_id' => 'ID-DIY-YOG',
    'role' => 'viewer',
]);

echo "PI_R93_CI_USERS_CREATED=2\n";
echo "PI_R93_CI_DB=ISOLATED_RUNNER_TEMP_ONLY\n";
