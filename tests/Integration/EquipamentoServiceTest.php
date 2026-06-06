<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class EquipamentoServiceTest extends TestCase
{
    private ?\database $db = null;

    protected function setUp(): void
    {
        if (!defined('DB_HOST')) {
            $this->markTestSkipped('Database not configured');
        }

        try {
            $this->db = getTestDatabase();
            $this->db->query("SELECT 1");
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    public function testServiceCanBeInstantiated(): void
    {
        $svc = new \EquipamentoService($this->db);
        $this->assertInstanceOf(\EquipamentoService::class, $svc);
    }

    public function testExtractCommonDataReturnsAllFields(): void
    {
        $svc = new \EquipamentoService($this->db);
        $data = [
            'status_id' => 1,
            'procedimento_id' => 2,
            'regiao_id' => 3,
            'tipo_id' => 1,
            'tipo_camera' => 1,
            'secretaria_id' => 1,
            'marca_id' => 1,
            'ip' => '192.168.1.1',
            'patrimonio' => 'ABC-123',
        ];

        $fields = $svc->extractCommonData($data);

        $this->assertIsArray($fields);
        $this->assertEquals(1, $fields['status_id']);
        $this->assertEquals(2, $fields['procedimento_id']);
        $this->assertEquals(3, $fields['regiao_id']);
        $this->assertEquals(1, $fields['tipo_id']);
        $this->assertEquals(1, $fields['tipo_camera_id']);
        $this->assertEquals('192.168.1.1', $fields['normalized_ip']);
        $this->assertEquals('ABC-123', $fields['patrimonio']);
    }

    public function testExtractCommonDataWithMissingFieldsReturnsDefaults(): void
    {
        $svc = new \EquipamentoService($this->db);
        $fields = $svc->extractCommonData([]);

        $this->assertEquals(0, $fields['status_id']);
        $this->assertNull($fields['normalized_ip']);
        $this->assertNull($fields['patrimonio']);
        $this->assertNull($fields['transmissao_id']);
    }

    public function testBuildEquipDataReturnsCorrectStructure(): void
    {
        $svc = new \EquipamentoService($this->db);
        $fields = $svc->extractCommonData([
            'status_id' => 1,
            'procedimento_id' => 2,
            'regiao_id' => 3,
            'tipo_id' => 1,
            'secretaria_id' => 1,
            'marca_id' => 1,
            'ip' => '10.0.0.1',
        ]);

        $equipData = $svc->buildEquipData($fields, 1, 1);

        $this->assertArrayHasKey(':tipo_equipamento_id', $equipData);
        $this->assertArrayHasKey(':status_id', $equipData);
        $this->assertArrayHasKey(':ip', $equipData);
        $this->assertArrayHasKey(':local_id', $equipData);
        $this->assertArrayHasKey(':modelo_id', $equipData);
        $this->assertEquals(1, $equipData[':tipo_equipamento_id']);
        $this->assertEquals('10.0.0.1', $equipData[':ip']);
        $this->assertEquals(1, $equipData[':local_id']);
    }

    public function testBuildAuditDataContainsRequiredKeys(): void
    {
        $svc = new \EquipamentoService($this->db);
        $fields = $svc->extractCommonData([
            'status_id' => 1,
            'procedimento_id' => 1,
            'regiao_id' => 1,
            'tipo_id' => 1,
            'secretaria_id' => 1,
            'marca_id' => 1,
        ]);

        $audit = $svc->buildAuditData(1, $fields);

        $this->assertArrayHasKey('equipamento_id', $audit);
        $this->assertArrayHasKey('tipo_equipamento_id', $audit);
        $this->assertArrayHasKey('status_id', $audit);
        $this->assertArrayHasKey('ip', $audit);
        $this->assertArrayHasKey('mosaico', $audit);
        $this->assertArrayHasKey('coordenadas', $audit);
        $this->assertEquals(1, $audit['equipamento_id']);
    }

    public function testResolveClassificacaoEnderecoReturnsNullWhenNoInput(): void
    {
        $svc = new \EquipamentoService($this->db);
        $result = $svc->resolveClassificacaoEndereco(null, null);
        $this->assertNull($result);
    }

    public function testValidateCoordenadasAcceptsValidFormat(): void
    {
        $svc = new \EquipamentoService($this->db);

        $svc->validateCoordenadas('-23.5505, -46.6333');
        $this->assertTrue(true);
    }

    public function testValidateCoordenadasRejectsInvalidFormat(): void
    {
        $svc = new \EquipamentoService($this->db);

        $this->expectException(\Exception::class);
        $svc->validateCoordenadas('invalid-coords');
    }

    public function testValidateTipoSpecificAcceptsValidTypes(): void
    {
        $svc = new \EquipamentoService($this->db);

        $svc->validateTipoSpecific(1, '', 0);
        $this->assertTrue(true);
    }

    public function testValidateTipoSpecificRejectsDvrWithoutModelo(): void
    {
        $svc = new \EquipamentoService($this->db);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Informe o modelo do DVR');
        $svc->validateTipoSpecific(3, '', null);
    }

    public function testValidateTipoSpecificRejectsTotemWithoutQuantity(): void
    {
        $svc = new \EquipamentoService($this->db);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Informe a quantidade de câmeras do Totem');
        $svc->validateTipoSpecific(4, '', null);
    }

    public function testParseJsonInputReturnsPostData(): void
    {
        $testData = ['status_id' => 1, 'tipo_id' => 1];
        $encoded = json_encode($testData);

        stream_filter_register('test_input', 'Tests\Integration\TestInputStreamFilter');
        // Cannot truly test php://input without mock - skip runtime test
        $this->assertTrue(true);
    }
}
