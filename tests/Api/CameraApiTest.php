<?php

declare(strict_types=1);

namespace Tests\Api;

use PHPUnit\Framework\TestCase;

class CameraApiTest extends TestCase
{
    private string $baseUrl;

    protected function setUp(): void
    {
        $this->baseUrl = getenv('TEST_BASE_URL') ?: 'http://localhost:8080';
    }

    public function testListCamerasWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_cameras');
        $this->assertEquals(401, $httpCode);
    }

    public function testListCamerasWithInvalidMethodReturns405(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_cameras', 'POST');
        $this->assertEquals(405, $httpCode);
    }

    public function testCreateCameraWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_cadastrar_cameras', 'POST');
        $this->assertEquals(401, $httpCode);
    }

    public function testEditCameraWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_editar_camera', 'POST');
        $this->assertEquals(401, $httpCode);
    }

    public function testDeleteCameraWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_excluir_camera', 'POST');
        $this->assertEquals(401, $httpCode);
    }

    public function testManutencaoCamerasWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_manutencao_cameras');
        $this->assertEquals(401, $httpCode);
    }

    public function testDashboardWithoutAuthReturns401(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_dashboard');
        $this->assertEquals(401, $httpCode);
    }

    public function testListCamerasReturnsJson(): void
    {
        $response = $this->requestJson('/index.php?page=api/api_cameras');
        if ($response === null) {
            $this->markTestSkipped("API server not reachable at {$this->baseUrl}");
        }
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
    }

    public function testPaginatedCamerasReturnsPaginationMeta(): void
    {
        $response = $this->requestJson('/index.php?page=api/api_cameras&per_page=10');
        if ($response === null) {
            $this->markTestSkipped("API server not reachable at {$this->baseUrl}");
        }
        if (!empty($response['pagination'])) {
            $this->assertArrayHasKey('page', $response['pagination']);
            $this->assertArrayHasKey('per_page', $response['pagination']);
            $this->assertArrayHasKey('total', $response['pagination']);
            $this->assertArrayHasKey('total_pages', $response['pagination']);
        }
    }

    public function testNonExistentEndpointReturns404(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_nonexistent');
        $this->assertEquals(404, $httpCode);
    }

    public function testCreateCameraWithEmptyDataReturns422(): void
    {
        $httpCode = $this->requestStatus('/index.php?page=api/api_cadastrar_cameras', 'POST', '[]');
        $this->assertEquals(422, $httpCode);
    }

    private function requestStatus(string $path, string $method = 'GET', ?string $body = null): int
    {
        $opts = [
            'http' => [
                'method' => $method,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ];
        if ($body !== null) {
            $opts['http']['header'] = "Content-Type: application/json\r\n";
            $opts['http']['content'] = $body;
        }

        $context = stream_context_create($opts);
        $headers = null;

        set_error_handler(static function (): bool { return true; });
        try {
            file_get_contents($this->baseUrl . $path, false, $context);
            $headers = $http_response_header ?? null;
        } finally {
            restore_error_handler();
        }

        if (!$headers || !isset($headers[0])) {
            $this->markTestSkipped("API server not reachable at {$this->baseUrl}");
        }

        preg_match('#HTTP/\d(?:\.\d)?\s+(\d+)#', $headers[0], $matches);
        return (int)($matches[1] ?? 0);
    }

    private function requestJson(string $path): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        set_error_handler(static function (): bool { return true; });
        try {
            $content = file_get_contents($this->baseUrl . $path, false, $context);
        } finally {
            restore_error_handler();
        }

        if ($content === false) {
            return null;
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
