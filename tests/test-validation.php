#!/usr/bin/env php
<?php
/**
 * Teste do Sistema de Validação de Requisições
 * 
 * Executa: php tests/test-validation.php
 */

class Colors {
    const GREEN = "\033[92m";
    const RED = "\033[91m";
    const YELLOW = "\033[93m";
    const BLUE = "\033[94m";
    const RESET = "\033[0m";
}

function printTest($name, $passed, $details = '') {
    $status = $passed ? Colors::GREEN . "✅" . Colors::RESET : Colors::RED . "❌" . Colors::RESET;
    echo "  $status  $name\n";
    if ($details) {
        echo "      $details\n";
    }
}

function printSection($title) {
    echo "\n" . Colors::BLUE . "╔════════════════════════════════════════╗" . Colors::RESET . "\n";
    echo Colors::BLUE . "║ " . str_pad($title, 36) . " ║" . Colors::RESET . "\n";
    echo Colors::BLUE . "╚════════════════════════════════════════╝" . Colors::RESET . "\n\n";
}

// Setup
define('APP_ROOT', dirname(__DIR__));
define('API_ROOT', dirname(__DIR__) . '/api');

require_once API_ROOT . '/RequestValidator.php';
require_once API_ROOT . '/ValidationSchema.php';

$testsPassed = 0;
$totalTests = 0;

// ======================================
// TESTE 1: Carregamento
// ======================================

printSection("TESTE 1: Carregamento de Classes");

$totalTests++;
$validatorLoaded = class_exists('RequestValidator');
printTest("Classe RequestValidator carrega", $validatorLoaded);
if ($validatorLoaded) $testsPassed++;

$totalTests++;
$schemaLoaded = class_exists('ValidationSchema');
printTest("Classe ValidationSchema carrega", $schemaLoaded);
if ($schemaLoaded) $testsPassed++;

// ======================================
// TESTE 2: Validações Básicas
// ======================================

printSection("TESTE 2: Validações Básicas");

// Required
$totalTests++;
$validator = new RequestValidator(['nome' => 'João']);
$validator->validate(['nome' => 'required']);
$pass = $validator->passes();
printTest("Required passa com valor", $pass);
if ($pass) $testsPassed++;

$totalTests++;
$validator = new RequestValidator([]);
$validator->validate(['nome' => 'required']);
$fail = $validator->fails();
printTest("Required falha sem valor", $fail);
if ($fail) $testsPassed++;

// String
$totalTests++;
$validator = new RequestValidator(['nome' => 'João']);
$validator->validate(['nome' => 'string']);
$pass = $validator->passes();
printTest("String passa com texto", $pass);
if ($pass) $testsPassed++;

// Numeric
$totalTests++;
$validator = new RequestValidator(['idade' => 25]);
$validator->validate(['idade' => 'numeric']);
$pass = $validator->passes();
printTest("Numeric passa com número", $pass);
if ($pass) $testsPassed++;

// Email
$totalTests++;
$validator = new RequestValidator(['email' => 'test@example.com']);
$validator->validate(['email' => 'email']);
$pass = $validator->passes();
printTest("Email passa com email válido", $pass);
if ($pass) $testsPassed++;

$totalTests++;
$validator = new RequestValidator(['email' => 'invalid-email']);
$validator->validate(['email' => 'email']);
$fail = $validator->fails();
printTest("Email falha com email inválido", $fail);
if ($fail) $testsPassed++;

// ======================================
// TESTE 3: Validações de Tamanho
// ======================================

printSection("TESTE 3: Validações de Tamanho");

// Min
$totalTests++;
$validator = new RequestValidator(['senha' => 'abc123456']);
$validator->validate(['senha' => 'min:6']);
$pass = $validator->passes();
printTest("Min passa com comprimento suficiente", $pass);
if ($pass) $testsPassed++;

$totalTests++;
$validator = new RequestValidator(['senha' => 'abc']);
$validator->validate(['senha' => 'min:6']);
$fail = $validator->fails();
printTest("Min falha com comprimento insuficiente", $fail);
if ($fail) $testsPassed++;

// Max
$totalTests++;
$validator = new RequestValidator(['nome' => 'João Silva']);
$validator->validate(['nome' => 'max:20']);
$pass = $validator->passes();
printTest("Max passa com comprimento válido", $pass);
if ($pass) $testsPassed++;

$totalTests++;
$validator = new RequestValidator(['nome' => 'João Silva Lima Ferreira Oliveira']);
$validator->validate(['nome' => 'max:20']);
$fail = $validator->fails();
printTest("Max falha com comprimento excedido", $fail);
if ($fail) $testsPassed++;

// ======================================
// TESTE 4: Validações Especiais
// ======================================

printSection("TESTE 4: Validações Especiais");

// CPF
$totalTests++;
$validator = new RequestValidator(['cpf' => '123.456.789-09']);
$validator->validate(['cpf' => 'cpf']);
$pass = !$validator->fails();  // CPF inválido, mas formato é aceito
printTest("CPF aceita formato", $pass);
if ($pass) $testsPassed++;

// URL
$totalTests++;
$validator = new RequestValidator(['url' => 'https://example.com']);
$validator->validate(['url' => 'url']);
$pass = $validator->passes();
printTest("URL passa com URL válida", $pass);
if ($pass) $testsPassed++;

// IP
$totalTests++;
$validator = new RequestValidator(['ip' => '192.168.1.1']);
$validator->validate(['ip' => 'ip']);
$pass = $validator->passes();
printTest("IP passa com IP válido", $pass);
if ($pass) $testsPassed++;

$totalTests++;
$validator = new RequestValidator(['ip' => '256.256.256.256']);
$validator->validate(['ip' => 'ip']);
$fail = $validator->fails();
printTest("IP falha com IP inválido", $fail);
if ($fail) $testsPassed++;

// UUID
$totalTests++;
$validator = new RequestValidator(['id' => '550e8400-e29b-41d4-a716-446655440000']);
$validator->validate(['id' => 'uuid']);
$pass = $validator->passes();
printTest("UUID passa com UUID válido", $pass);
if ($pass) $testsPassed++;

// ======================================
// TESTE 5: In/NotIn
// ======================================

printSection("TESTE 5: Validações de Lista");

$totalTests++;
$validator = new RequestValidator(['status' => 'ativo']);
$validator->validate(['status' => 'in:ativo,inativo,pendente']);
$pass = $validator->passes();
printTest("In passa com valor na lista", $pass);
if ($pass) $testsPassed++;

$totalTests++;
$validator = new RequestValidator(['status' => 'invalido']);
$validator->validate(['status' => 'in:ativo,inativo,pendente']);
$fail = $validator->fails();
printTest("In falha com valor fora da lista", $fail);
if ($fail) $testsPassed++;

// ======================================
// TESTE 6: Validação Schema
// ======================================

printSection("TESTE 6: Validação com Schema");

$totalTests++;
$schemas = ValidationSchema::all();
$hasSchemas = count($schemas) > 0;
printTest("Schemas foram carregados", $hasSchemas, count($schemas) . " schemas");
if ($hasSchemas) $testsPassed++;

$totalTests++;
$schema = ValidationSchema::get('POST', 'cameras');
$schemaExists = $schema !== null;
printTest("Schema para POST cameras existe", $schemaExists);
if ($schemaExists) $testsPassed++;

// ======================================
// TESTE 7: Múltiplos Erros
// ======================================

printSection("TESTE 7: Múltiplos Erros");

$totalTests++;
$validator = new RequestValidator([
    'nome' => '',
    'email' => 'invalid',
    'idade' => 'abc'
]);
$validator->validate([
    'nome' => 'required|string',
    'email' => 'required|email',
    'idade' => 'required|numeric'
]);

$errors = $validator->errors();
$hasErrors = count($errors) >= 2;  // Deve ter erros em vários campos
printTest("Múltiplos erros são capturados", $hasErrors, count($errors) . " campos com erro");
if ($hasErrors) $testsPassed++;

// ======================================
// TESTE 8: Dados Válidos
// ======================================

printSection("TESTE 8: Métodos de Extração");

$totalTests++;
$validator = new RequestValidator([
    'nome' => 'João',
    'email' => 'joao@example.com',
    'senha' => 'secret123'
]);
$validator->validate([
    'nome' => 'required|string',
    'email' => 'required|email',
    'senha' => 'required|string|min:6'
]);

$validated = $validator->validated();
$isValid = !empty($validated) && count($validated) === 3;
printTest("Dados validados retornam corretamente", $isValid, count($validated) . " campos válidos");
if ($isValid) $testsPassed++;

$totalTests++;
$only = $validator->only(['nome', 'email']);
$onlyValid = count($only) === 2 && isset($only['nome']) && isset($only['email']);
printTest("Método only extrai campos corretos", $onlyValid);
if ($onlyValid) $testsPassed++;

// ======================================
// RESUMO
// ======================================

printSection("RESUMO");

$percentage = ($testsPassed / $totalTests) * 100;
$color = $percentage === 100 ? Colors::GREEN : ($percentage >= 80 ? Colors::YELLOW : Colors::RED);

echo $color . "  Testes Passados: $testsPassed/$totalTests (" . number_format($percentage, 1) . "%)" . Colors::RESET . "\n\n";

if ($percentage === 100) {
    echo Colors::GREEN . "  ✅ TODOS OS TESTES PASSARAM!" . Colors::RESET . "\n";
    echo "  Task 2 (Request Validator) está completa e funcionando.\n";
    exit(0);
} else {
    echo Colors::RED . "  ❌ ALGUNS TESTES FALHARAM" . Colors::RESET . "\n";
    exit(1);
}
