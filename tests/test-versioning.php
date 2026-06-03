#!/usr/bin/env php
<?php

class Colors {
    const GREEN = "\033[92m";
    const RED = "\033[91m";
    const YELLOW = "\033[93m";
    const BLUE = "\033[94m";
    const RESET = "\033[0m";
}

function printTest($name, $passed, $details = '') {
    $status = $passed ? Colors::GREEN . "✅ PASS" . Colors::RESET : Colors::RED . "❌ FAIL" . Colors::RESET;
    echo "  $status  $name\n";
    if ($details) {
        echo "         $details\n";
    }
}

function printSection($title) {
    echo "\n" . Colors::BLUE . "╔════════════════════════════════════════╗" . Colors::RESET . "\n";
    echo Colors::BLUE . "║ " . str_pad($title, 36) . " ║" . Colors::RESET . "\n";
    echo Colors::BLUE . "╚════════════════════════════════════════╝" . Colors::RESET . "\n\n";
}

printSection("TESTE 1: Estrutura de Diretórios");

$apiPath = __DIR__ . '/../api';
$testsPassed = 0;
$totalTests = 0;

$directories = ['v2'];
foreach ($directories as $dir) {
    $totalTests++;
    $exists = is_dir("$apiPath/$dir");
    printTest("Diretório /api/$dir existe", $exists);
    if ($exists) $testsPassed++;
}

$mainFiles = ['bootstrap-api.php', 'ApiResponse.php', 'RateLimiter.php'];
foreach ($mainFiles as $file) {
    $totalTests++;
    $exists = file_exists("$apiPath/$file");
    printTest("Arquivo $file existe", $exists);
    if ($exists) $testsPassed++;
}

$totalTests++;
$v2Files = glob("$apiPath/v2/api_*.php");
$v2Count = count($v2Files);
$hasV2Files = $v2Count > 0;
printTest("Arquivos API em v2", $hasV2Files, "($v2Count arquivos)");
if ($hasV2Files) $testsPassed++;

printSection("TESTE 2: Carregamento de Classes");

define('APP_ROOT', dirname(__DIR__));
define('API_ROOT', dirname(__DIR__) . '/api');

$totalTests++;
$responseLoaded = false;
try {
    require_once API_ROOT . '/ApiResponse.php';
    $responseLoaded = class_exists('ApiResponse');
    printTest("Classe ApiResponse carrega", $responseLoaded);
    if ($responseLoaded) $testsPassed++;
} catch (Exception $e) {
    printTest("Classe ApiResponse carrega", false, $e->getMessage());
}

printSection("TESTE 3: Métodos ApiResponse");

if ($responseLoaded) {
    $totalTests++;
    $requestId = ApiResponse::getRequestId();
    $isValid = !empty($requestId) && strpos($requestId, 'req_') === 0;
    printTest("Request ID gerado", $isValid, "ID: $requestId");
    if ($isValid) $testsPassed++;

    $totalTests++;
    $requestId2 = ApiResponse::getRequestId();
    $sameId = $requestId === $requestId2;
    printTest("Request ID consistente", $sameId);
    if ($sameId) $testsPassed++;
}

printSection("TESTE 4: API Response Meta sem ApiRouter");

if ($responseLoaded) {
    $totalTests++;
    try {
        $reflection = new ReflectionMethod(ApiResponse::class, 'getMeta');
        $reflection->setAccessible(true);
        $meta = $reflection->invoke(null);
        $hasVersion = isset($meta['version']) && $meta['version'] === 'v2';
        printTest("Meta retorna versão v2 sem ApiRouter", $hasVersion, "Versão: " . ($meta['version'] ?? 'N/A'));
        if ($hasVersion) $testsPassed++;
    } catch (Exception $e) {
        printTest("Meta retorna versão v2 sem ApiRouter", false, $e->getMessage());
    }
}

printSection("RESUMO");

$percentage = ($testsPassed / $totalTests) * 100;
$color = $percentage === 100 ? Colors::GREEN : ($percentage >= 80 ? Colors::YELLOW : Colors::RED);

echo $color . "  Testes Passados: $testsPassed/$totalTests (" . number_format($percentage, 1) . "%)" . Colors::RESET . "\n\n";

if ($percentage === 100) {
    echo Colors::GREEN . "  ✅ TODOS OS TESTES PASSARAM!" . Colors::RESET . "\n";
    exit(0);
} else {
    echo Colors::RED . "  ❌ ALGUNS TESTES FALHARAM" . Colors::RESET . "\n";
    exit(1);
}
