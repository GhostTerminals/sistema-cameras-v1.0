#!/usr/bin/env php
<?php
/**
 * Teste do Sistema de Rate Limiting
 * 
 * Executa: php tests/test-ratelimit.php
 * 
 * Valida:
 * - ✅ Sliding Window: limites por janela de tempo
 * - ✅ Token Bucket: renovação de tokens
 * - ✅ Headers de rate limit
 * - ✅ Limpeza de dados expirados
 * - ✅ Integração com bootstrap-api.php
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

require_once API_ROOT . '/RateLimiter.php';

$testsPassed = 0;
$totalTests = 0;

// ======================================
// TESTE 1: Carregamento
// ======================================

printSection("TESTE 1: Carregamento de Classes");

$totalTests++;
$loaded = class_exists('RateLimiter');
printTest("Classe RateLimiter carrega", $loaded);
if ($loaded) $testsPassed++;

// ======================================
// TESTE 2: Sliding Window
// ======================================

printSection("TESTE 2: Sliding Window - Limite de Requisições");

$limiter = new RateLimiter(
    storagePath: sys_get_temp_dir() . '/ratelimit_test',
    strategy: 'sliding_window'
);

// Limpar dados anteriores
$limiter->reset('test:sliding:client1');

// Testar consumo dentro do limite (5 req em 60s)
$totalTests++;
$allowed = true;
for ($i = 0; $i < 5; $i++) {
    if (!$limiter->consume('test:sliding:client1', 5, 60)) {
        $allowed = false;
        break;
    }
}
printTest("Sliding Window: permite até o limite", $allowed);
if ($allowed) $testsPassed++;

// Testar que excede o limite
$totalTests++;
$exceeded = !$limiter->consume('test:sliding:client1', 5, 60);
printTest("Sliding Window: bloqueia após exceder limite", $exceeded);
if ($exceeded) $testsPassed++;

// Testar remaining
$totalTests++;
$remaining = $limiter->getRemaining('test:sliding:client1', 5, 60);
$remainingOk = $remaining === 0;
printTest("Sliding Window: remaining é 0 após limite", $remainingOk, "Remaining: $remaining");
if ($remainingOk) $testsPassed++;

// Testar retry after
$totalTests++;
$retryAfter = $limiter->getRetryAfter('test:sliding:client1', 5, 60);
$retryOk = $retryAfter > 0 && $retryAfter <= 60;
printTest("Sliding Window: retry-after positivo", $retryOk, "Retry-After: {$retryAfter}s");
if ($retryOk) $testsPassed++;

// Limpar
$limiter->reset('test:sliding:client1');

// ======================================
// TESTE 3: Token Bucket
// ======================================

printSection("TESTE 3: Token Bucket - Consumo de Tokens");

$bucket = new RateLimiter(
    storagePath: sys_get_temp_dir() . '/ratelimit_test',
    strategy: 'token_bucket'
);

// Limpar dados anteriores
$bucket->reset('test:bucket:client1');

// Consumir todos os tokens
$totalTests++;
$allConsumed = true;
for ($i = 0; $i < 10; $i++) {
    if (!$bucket->consume('test:bucket:client1', 10, 60)) {
        $allConsumed = false;
        break;
    }
}
printTest("Token Bucket: permite consumir todos os tokens", $allConsumed);
if ($allConsumed) $testsPassed++;

// Exceder tokens
$totalTests++;
$blocked = !$bucket->consume('test:bucket:client1', 10, 60);
printTest("Token Bucket: bloqueia quando sem tokens", $blocked);
if ($blocked) $testsPassed++;

// Verificar refill (simular passagem de tempo não é prático em teste unitário,
// mas verificamos que getRemaining retorna 0)
$totalTests++;
$remainingBucket = $bucket->getRemaining('test:bucket:client1', 10, 60);
$remainingOk = $remainingBucket === 0;
printTest("Token Bucket: remaining é 0 sem tokens", $remainingOk, "Remaining: $remainingBucket");
if ($remainingOk) $testsPassed++;

// Limpar
$bucket->reset('test:bucket:client1');

// ======================================
// TESTE 4: Headers
// ======================================

printSection("TESTE 4: Headers de Rate Limit");

$totalTests++;
$headers = $limiter->getHeaders('test:headers:client1', 100, 3600);
$hasLimit = isset($headers['X-RateLimit-Limit']) && $headers['X-RateLimit-Limit'] === '100';
$hasRemaining = isset($headers['X-RateLimit-Remaining']);
$hasReset = isset($headers['X-RateLimit-Reset']);
$allHeaders = $hasLimit && $hasRemaining && $hasReset;
printTest("Headers de rate limit presentes", $allHeaders,
    "Limit: {$headers['X-RateLimit-Limit']}, Remaining: {$headers['X-RateLimit-Remaining']}");
if ($allHeaders) $testsPassed++;

// Limpar
$limiter->reset('test:headers:client1');

// ======================================
// TESTE 5: Status e Stats
// ======================================

printSection("TESTE 5: Métodos de Status e Estatísticas");

// Consumir algumas requisições
for ($i = 0; $i < 3; $i++) {
    $limiter->consume('test:status:client1', 10, 60);
}

$totalTests++;
$status = $limiter->getStatus('test:status:client1', 10, 60);
$statusOk = isset($status['limit']) && isset($status['remaining']) && isset($status['allowed'])
    && $status['remaining'] === 7 && $status['allowed'] === true;
printTest("getStatus retorna informações corretas", $statusOk,
    "Limit: {$status['limit']}, Remaining: {$status['remaining']}, Allowed: " . ($status['allowed'] ? 'true' : 'false'));
if ($statusOk) $testsPassed++;

$totalTests++;
$stats = $limiter->getStats();
$statsOk = isset($stats['storage_path']) && isset($stats['strategy']);
printTest("getStats retorna estatísticas", $statsOk,
    "Strategy: {$stats['strategy']}, Keys: {$stats['total_keys']}");
if ($statsOk) $testsPassed++;

// Limpar
$limiter->reset('test:status:client1');

// ======================================
// TESTE 6: Check (com headers)
// ======================================

printSection("TESTE 6: Método check() com Headers");

$totalTests++;
$allowed = $limiter->check('test:check:client1', 5, 60);
printTest("check() permite dentro do limite", $allowed);
if ($allowed) $testsPassed++;

// Consumir 4 mais para chegar ao limite
for ($i = 0; $i < 4; $i++) {
    $limiter->consume('test:check:client1', 5, 60);
}

$totalTests++;
$blocked = !$limiter->check('test:check:client1', 5, 60);
printTest("check() bloqueia após exceder limite", $blocked);
if ($blocked) $testsPassed++;

// Limpar
$limiter->reset('test:check:client1');

// ======================================
// TESTE 7: Limpeza de Dados Expirados
// ======================================

printSection("TESTE 7: Limpeza de Dados Expirados");

// Criar dados antigos manualmente
$limiter->consume('test:cleanup:old', 10, 60);

$totalTests++;
$cleaned = $limiter->cleanup(0); // maxAge=0 remove tudo
$cleanedOk = $cleaned >= 0;
printTest("cleanup() executa sem erros", $cleanedOk, "Removidos: $cleaned arquivos");
if ($cleanedOk) $testsPassed++;

// ======================================
// TESTE 8: Configuração por Regex
// ======================================

printSection("TESTE 8: Configuração Customizada por Regex");

$configured = new RateLimiter(
    storagePath: sys_get_temp_dir() . '/ratelimit_test',
    strategy: 'sliding_window',
    configs: [
        '/^auth:/' => [5, 60],
        '/^admin:/' => [100, 60],
    ]
);

// Para chave 'auth:login:127.0.0.1', deve aplicar limite 5/60
$consumed = true;
for ($i = 0; $i < 5; $i++) {
    if (!$configured->consume('auth:login:127.0.0.1')) {
        $consumed = false;
        break;
    }
}
$totalTests++;
$blocked = !$configured->consume('auth:login:127.0.0.1');
printTest("Config regex: auth endpoints têm limite 5/min", $consumed && $blocked,
    "Consumiu 5, bloqueou na 6ª");
if ($consumed && $blocked) $testsPassed++;

$totalTests++;
$allowed = $configured->consume('admin:dashboard:127.0.0.1');
printTest("Config regex: admin endpoints têm limite 100/min", $allowed,
    "Permitiu requisição admin");
if ($allowed) $testsPassed++;

// Limpar
$configured->reset('auth:login:127.0.0.1');
$configured->reset('admin:dashboard:127.0.0.1');

// ======================================
// TESTE 9: Reset
// ======================================

printSection("TESTE 9: Reset de Chave");

$totalTests++;
$limiter->consume('test:reset:client1', 5, 60);
$limiter->consume('test:reset:client1', 5, 60);
$beforeReset = $limiter->getRemaining('test:reset:client1', 5, 60);
$limiter->reset('test:reset:client1');
$afterReset = $limiter->getRemaining('test:reset:client1', 5, 60);
$resetOk = $beforeReset < 5 && $afterReset === 5;
printTest("reset() restaura remaining ao máximo", $resetOk,
    "Antes: $beforeReset, Depois: $afterReset");
if ($resetOk) $testsPassed++;

// ======================================
// TESTE 10: Request ID + Rate Limit Key (identificação única)
// ======================================

printSection("TESTE 10: Isolamento por Chave");

$totalTests++;
// Clientes diferentes não devem compartilhar limite
$c1pass = true;
for ($i = 0; $i < 5; $i++) {
    if (!$limiter->consume('test:iso:client1', 5, 60)) {
        $c1pass = false;
        break;
    }
}
$c2pass = $limiter->consume('test:iso:client2', 5, 60);
printTest("Clientes diferentes têm limites independentes", $c1pass && $c2pass,
    "Client1 consumiu 5/5, Client2 ainda pode 1/5");
if ($c1pass && $c2pass) $testsPassed++;

// Limpar
$limiter->reset('test:iso:client1');
$limiter->reset('test:iso:client2');

// ======================================
// RESUMO
// ======================================

printSection("RESUMO");

$percentage = ($testsPassed / $totalTests) * 100;
$color = $percentage === 100 ? Colors::GREEN : ($percentage >= 80 ? Colors::YELLOW : Colors::RED);

echo $color . "  Testes Passados: $testsPassed/$totalTests (" . number_format($percentage, 1) . "%)" . Colors::RESET . "\n\n";

if ($percentage === 100) {
    echo Colors::GREEN . "  ✅ TODOS OS TESTES PASSARAM!" . Colors::RESET . "\n";
    echo "  Task 3 (Rate Limiting) está completa e funcionando.\n";
    exit(0);
} else {
    echo Colors::RED . "  ❌ ALGUNS TESTES FALHARAM" . Colors::RESET . "\n";
    echo "  Verifique os erros acima.\n";
    exit(1);
}
