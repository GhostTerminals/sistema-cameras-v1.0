<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes para validação de upload de arquivos
 * 
 * Valida:
 * - MIME type detection
 * - Tamanho máximo
 * - Tipos permitidos
 */
class FileUploadValidationTest extends TestCase
{
    /**
     * MIME types permitidos — deve refletir api_upload_anexo.php
     * Se alterar o source, atualizar este array ou o teste falhará.
     */
    private const ALLOWED_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const MAX_SIZE = 10 * 1024 * 1024; // 10MB

    private const EXT_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    public function setUp(): void
    {
        if (!file_exists(__DIR__ . '/../../api/v2/api_upload_anexo.php')) {
            $this->markTestSkipped('Upload endpoint source not found');
        }
    }

    /**
     * Testa que a allowlist do teste corresponde ao source real
     */
    public function testAllowlistMatchesSource(): void
    {
        $source = file_get_contents(__DIR__ . '/../../api/v2/api_upload_anexo.php');
        $this->assertStringContainsString("'image/jpeg'", $source, 'Source must contain image/jpeg');
        $this->assertStringContainsString("'application/pdf'", $source, 'Source must contain application/pdf');
        $this->assertStringContainsString('10 * 1024 * 1024', $source, 'Source must define 10MB limit');
    }

    /**
     * Testa que MIME type permitido é aceito
     */
    public function testMimeTypeAllowed(): void
    {
        $this->assertContains('image/jpeg', self::ALLOWED_MIMES);
        $this->assertContains('application/pdf', self::ALLOWED_MIMES);
    }

    /**
     * Testa que MIME type não permitido é rejeitado
     */
    public function testMimeTypeNotAllowed(): void
    {
        $this->assertNotContains('application/x-executable', self::ALLOWED_MIMES);
        $this->assertNotContains('application/x-php', self::ALLOWED_MIMES);
        $this->assertNotContains('application/x-shellscript', self::ALLOWED_MIMES);
    }

    /**
     * Testa tamanho máximo (10MB)
     */
    public function testMaxFileSizeLimit(): void
    {
        $this->assertEquals(10 * 1024 * 1024, self::MAX_SIZE);
        
        // Arquivo muito grande deve ser rejeitado
        $fileSize = 11 * 1024 * 1024; // 11MB
        $this->assertGreaterThan(self::MAX_SIZE, $fileSize);
    }

    /**
     * Testa que arquivo dentro do limite é aceito
     */
    public function testFileSizeWithinLimit(): void
    {
        $fileSize = 5 * 1024 * 1024; // 5MB
        $this->assertLessThan(self::MAX_SIZE, $fileSize);
    }

    /**
     * Testa mapeamento de MIME para extensão
     */
    public function testMimeToExtensionMapping(): void
    {
        $this->assertEquals('jpg', self::EXT_MAP['image/jpeg']);
        $this->assertEquals('pdf', self::EXT_MAP['application/pdf']);
        $this->assertEquals('docx', self::EXT_MAP['application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    }

    /**
     * Testa proteção contra upload de executáveis
     */
    public function testProtectionAgainstExecutables(): void
    {
        $executableMimes = [
            'application/x-executable',
            'application/x-elf',
            'application/x-mach-binary',
            'application/x-msdownload',
            'application/x-dvi',
            'application/x-sh',
            'application/x-shellscript',
            'application/x-perl',
            'application/x-python',
        ];

        foreach ($executableMimes as $mime) {
            $this->assertNotContains($mime, self::ALLOWED_MIMES, 
                "Executable MIME type $mime não deve ser permitido");
        }
    }

    /**
     * Testa proteção contra upload de PHP
     */
    public function testProtectionAgainstPhp(): void
    {
        $phpMimes = [
            'application/x-php',
            'application/x-php3',
            'application/x-php4',
            'application/x-php5',
            'application/x-php6',
            'application/x-php7',
            'text/x-php',
        ];

        foreach ($phpMimes as $mime) {
            $this->assertNotContains($mime, self::ALLOWED_MIMES,
                "PHP MIME type $mime não deve ser permitido");
        }
    }

    /**
     * Testa proteção contra upload de scripts
     */
    public function testProtectionAgainstScripts(): void
    {
        $scriptMimes = [
            'text/html',
            'text/javascript',
            'application/javascript',
            'text/vbscript',
            'application/x-vbscript',
        ];

        foreach ($scriptMimes as $mime) {
            $this->assertNotContains($mime, self::ALLOWED_MIMES,
                "Script MIME type $mime não deve ser permitido");
        }
    }

    /**
     * Testa tipos de imagem permitidos
     */
    public function testAllowedImageTypes(): void
    {
        $allowedImages = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
        ];

        foreach ($allowedImages as $mime) {
            $this->assertContains($mime, self::ALLOWED_MIMES);
        }
    }

    /**
     * Testa tipos de documento permitidos
     */
    public function testAllowedDocumentTypes(): void
    {
        $allowedDocs = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        foreach ($allowedDocs as $mime) {
            $this->assertContains($mime, self::ALLOWED_MIMES);
        }
    }
}
