<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

class HealthTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('TEST_BASE_URL') ?: 'http://localhost:8080';
    }

    public function testHealthWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_health');

        $this->assertEquals(401, $httpCode, 'Health endpoint should require auth');
    }

    public function testProtectedEndpointWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/session_check');

        $this->assertEquals(401, $httpCode, 'Protected endpoint should require auth');
    }

    public function testInvalidMethodReturns405(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_health', 'POST');

        $this->assertEquals(405, $httpCode, 'POST on GET-only endpoint should return 405');
    }

    private function requestStatus(string $path, string $method = 'GET'): int
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        $headers = null;
        set_error_handler(static function (): bool {
            return true;
        });

        try {
            file_get_contents($this->baseUrl . $path, false, $context);
            $headers = $http_response_header ?? null;
        } finally {
            restore_error_handler();
        }

        if (!$headers || !isset($headers[0])) {
            $this->markTestSkipped("API server is not reachable at {$this->baseUrl}");
        }

        preg_match('#HTTP/\d(?:\.\d)?\s+(\d+)#', $headers[0], $matches);

        return (int)($matches[1] ?? 0);
    }
}
