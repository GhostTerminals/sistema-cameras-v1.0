<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para funções de segurança
 * 
 * Valida:
 * - Hash de senha com bcrypt
 * - Verificação de senha
 * - Política de senha
 * - CSRF token
 */
class SecurityTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testHashPasswordUseBcrypt(): void
    {
        $password = 'MySecurePass123!';
        $hash = hashPassword($password);

        $this->assertStringStartsWith('$2', $hash);
        $this->assertGreaterThanOrEqual(60, strlen($hash));
    }

    public function testVerifyPasswordCorrect(): void
    {
        $password = 'MySecurePass123!';
        $hash = hashPassword($password);

        $this->assertTrue(verifyPassword($password, $hash));
    }

    public function testVerifyPasswordIncorrect(): void
    {
        $password = 'MySecurePass123!';
        $hash = hashPassword($password);

        $this->assertFalse(verifyPassword('WrongPassword123!', $hash));
    }

    public function testPasswordPolicyMinLength(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('12345', $errors);

        $this->assertFalse($result);
        $this->assertNotEmpty($errors);
    }

    public function testPasswordPolicyValid(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('MySecure1', $errors);

        $this->assertTrue($result);
        $this->assertEmpty($errors);
    }

    public function testPasswordPolicyNumericOnlyFails(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('12345678', $errors);

        $this->assertFalse($result);
        $this->assertNotEmpty($errors);
    }

    public function testPasswordPolicyShortFails(): void
    {
        $errors = [];
        $result = validatePasswordPolicy('Ab1', $errors);

        $this->assertFalse($result);
        $this->assertNotEmpty($errors);
    }

    public function testGenerateCsrfToken(): void
    {
        $_SESSION['csrf_token'] = null;
        
        $token = getCsrfToken();

        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testValidateCsrfTokenCorrect(): void
    {
        $_SESSION['csrf_token'] = null;
        
        $token = getCsrfToken();
        $valid = validateCsrfToken($token);

        $this->assertTrue($valid);
    }

    public function testValidateCsrfTokenIncorrect(): void
    {
        $_SESSION['csrf_token'] = 'valid_token_123456789012345678901234567890123456789012345';
        
        $valid = validateCsrfToken('invalid_token_123456789012345678901234567890123456789012');

        $this->assertFalse($valid);
    }

    public function testAccessLevelMapping(): void
    {
        $this->assertEquals('admin', mapAccessIdToName(3));
        $this->assertEquals('supervisor', mapAccessIdToName(2));
        $this->assertEquals('user', mapAccessIdToName(1));
        $this->assertNull(mapAccessIdToName(99));
    }

    public function testGenerateTemporaryPassword(): void
    {
        $password = generateTemporaryPassword(12);

        $this->assertEquals(12, strlen($password));
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9!@#$%&]+$/', $password);
        $this->assertMatchesRegularExpression('/[a-zA-Z]/', $password, 'Deve conter letras');
        $this->assertMatchesRegularExpression('/[0-9]/', $password, 'Deve conter numeros');
    }

    public function testDifferentPasswordsDifferentHashes(): void
    {
        $hash1 = hashPassword('Password123!');
        $hash2 = hashPassword('Password456!');

        $this->assertNotEquals($hash1, $hash2);
    }
}
