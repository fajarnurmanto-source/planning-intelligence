<?php

namespace Tests\Unit;

use App\Http\Middleware\LoginDiagnostics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;

final class LoginDiagnosticsTest extends TestCase
{
    public function test_only_allowlisted_metadata_is_logged_for_each_failure_class(): void
    {
        $logger = new class {
            public array $records = [];
            public function info($message, $context): void { $this->records[] = [$message, $context]; }
        };
        Log::swap($logger);
        try {
            foreach ([401 => 'INVALID_CREDENTIALS', 419 => 'CSRF_REJECTED', 422 => 'VALIDATION_REJECTED', 429 => 'LOGIN_RATE_LIMITED'] as $status => $expected) {
                $request = Request::create('/api/auth/login', 'POST', ['password' => 'SENTINEL_DO_NOT_LOG', 'username' => 'private']);
                $request->attributes->set('pi_login_correlation', 'synthetic-correlation');
                $payload = ['message' => 'SENTINEL_DO_NOT_LOG', 'errors' => ['username' => ['private validation text'], 'secret' => ['hidden']]];
                if (in_array($status, [401, 429], true)) $payload['code'] = $expected;
                (new LoginDiagnostics)->terminate($request, new JsonResponse($payload, $status));
                $record = $logger->records[array_key_last($logger->records)][1];
                self::assertSame($expected, $record['rejection_code']);
                self::assertSame(['correlation_id', 'http_status', 'rejection_code', 'validation_fields'], array_keys($record));
                self::assertSame($status === 422 ? ['username'] : [], $record['validation_fields']);
                self::assertStringNotContainsString('SENTINEL_DO_NOT_LOG', json_encode($record));
            }
        } finally { Log::clearResolvedInstance('log'); }
    }
}
