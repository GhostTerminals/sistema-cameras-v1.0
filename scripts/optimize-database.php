<?php
/**
 * Database Index Optimizer Script
 * 
 * Uso: php scripts/optimize-database.php
 * Objetivo: Aplicar índices de performance no banco de dados
 * 
 * IMPORTANTE: Fazer backup do banco ANTES de executar!
 */

declare(strict_types=1);

// Configurações
define('SCRIPT_VERSION', '1.0');
define('EXECUTION_START', microtime(true));

// Colors for CLI output
const COLOR_RESET = "\033[0m";
const COLOR_RED = "\033[31m";
const COLOR_GREEN = "\033[32m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_CYAN = "\033[36m";

// ==================================================================================
// FUNCTIONS
// ==================================================================================

function print_header(string $text): void
{
    echo "\n" . COLOR_CYAN . "╔════════════════════════════════════════════════════════════════╗" . COLOR_RESET . "\n";
    echo COLOR_CYAN . "║ " . str_pad($text, 62) . " ║" . COLOR_RESET . "\n";
    echo COLOR_CYAN . "╚════════════════════════════════════════════════════════════════╝" . COLOR_RESET . "\n\n";
}

function print_section(string $title): void
{
    echo COLOR_BLUE . "\n▶ " . $title . COLOR_RESET . "\n";
    echo str_repeat("─", 60) . "\n";
}

function print_success(string $message): void
{
    echo COLOR_GREEN . "  ✓ " . $message . COLOR_RESET . "\n";
}

function print_info(string $message): void
{
    echo COLOR_CYAN . "  ℹ " . $message . COLOR_RESET . "\n";
}

function print_warning(string $message): void
{
    echo COLOR_YELLOW . "  ⚠ " . $message . COLOR_RESET . "\n";
}

function print_error(string $message): void
{
    echo COLOR_RED . "  ✗ " . $message . COLOR_RESET . "\n";
}

function format_time(float $seconds): string
{
    if ($seconds < 0.001) {
        return round($seconds * 1000000) . " μs";
    } elseif ($seconds < 1) {
        return round($seconds * 1000, 2) . " ms";
    }
    return round($seconds, 2) . " s";
}

// ==================================================================================
// CONNECTION
// ==================================================================================

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
} catch (Throwable $e) {
    print_error("Falha ao carregar configuração: " . $e->getMessage());
    exit(1);
}

try {
    $db = db();
    $pdo = $db->getConnection();
    print_success("Conexão com banco de dados estabelecida");
} catch (Throwable $e) {
    print_error("Falha ao conectar no banco: " . $e->getMessage());
    exit(1);
}

// ==================================================================================
// ANÁLISE INICIAL
// ==================================================================================

print_header("🔍 DATABASE INDEX OPTIMIZER v" . SCRIPT_VERSION);

print_section("1. Analisando Índices Existentes");

try {
    // Tabelas principais
    $tables = ['equipamentos', 'central_alarmes', 'equipamentos_manutencoes'];
    $total_indexes = 0;
    $indexes_by_table = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW INDEX FROM {$table}");
        $indexes = $stmt->fetchAll(PDO::FETCH_OBJ);
        $indexes_by_table[$table] = count($indexes);
        $total_indexes += count($indexes);
        
        print_info("Tabela '{$table}': " . count($indexes) . " índices encontrados");
    }
    
    print_success("Total de índices: {$total_indexes}");
    
} catch (Throwable $e) {
    print_error("Erro ao analisar índices: " . $e->getMessage());
}

// ==================================================================================
// OTIMIZAÇÕES A APLICAR
// ==================================================================================

print_section("2. Aplicando Índices de Otimização");

// Define os índices a serem criados
$indexes = [
    // Campos de busca
    ['table' => 'equipamentos', 'name' => 'idx_equipamentos_ip', 'columns' => 'ip'],
    ['table' => 'equipamentos', 'name' => 'idx_equipamentos_numero_serie', 'columns' => 'numero_serie'],
    ['table' => 'central_alarmes', 'name' => 'idx_alarmes_ip', 'columns' => 'ip'],
    ['table' => 'central_alarmes', 'name' => 'idx_alarmes_conta', 'columns' => 'conta'],
    
    // Foreign keys
    ['table' => 'equipamentos', 'name' => 'idx_equipamentos_modelo_id', 'columns' => 'modelo_id'],
    ['table' => 'equipamentos', 'name' => 'idx_equipamentos_local_id', 'columns' => 'local_id'],
    ['table' => 'equipamentos', 'name' => 'idx_equipamentos_secretaria_id', 'columns' => 'secretaria_id'],
    ['table' => 'equipamentos', 'name' => 'idx_equipamentos_status_id', 'columns' => 'status_id'],
    ['table' => 'central_alarmes', 'name' => 'idx_alarmes_modelo_id', 'columns' => 'modelo_id'],
    
    // Filtros
    ['table' => 'equipamentos', 'name' => 'idx_equipamentos_tipo_equipamento_id', 'columns' => 'tipo_equipamento_id'],
    
    // Compostos
    ['table' => 'equipamentos', 'name' => 'idx_eq_tipo_status', 'columns' => 'tipo_equipamento_id, status_id'],
    ['table' => 'equipamentos', 'name' => 'idx_eq_local_tipo', 'columns' => 'local_id, tipo_equipamento_id'],
];

$created_count = 0;
$skipped_count = 0;
$failed_count = 0;

foreach ($indexes as $index) {
    $table = $index['table'];
    $name = $index['name'];
    $columns = $index['columns'];
    
    try {
        // Verificar se índice já existe
        $check = $pdo->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$name}'");
        if ($check->rowCount() > 0) {
            print_warning("Índice '{$name}' já existe em '{$table}'");
            $skipped_count++;
            continue;
        }
        
        // Criar índice
        $start = microtime(true);
        $pdo->exec("CREATE INDEX {$name} ON {$table}({$columns})");
        $elapsed = microtime(true) - $start;
        
        print_success("Índice '{$name}' criado em {$table} (" . format_time($elapsed) . ")");
        $created_count++;
        
    } catch (Throwable $e) {
        print_error("Erro ao criar '{$name}': " . $e->getMessage());
        $failed_count++;
    }
}

// ==================================================================================
// ANÁLISE FINAL
// ==================================================================================

print_section("3. Resumo da Otimização");

print_info("Índices criados: " . COLOR_GREEN . $created_count . COLOR_RESET);
print_info("Índices pulados: " . COLOR_YELLOW . $skipped_count . COLOR_RESET);
print_info("Erros: " . COLOR_RED . $failed_count . COLOR_RESET);

// Analisar tabelas
print_section("4. Analisando Tabelas");

foreach (['equipamentos', 'central_alarmes'] as $table) {
    try {
        $start = microtime(true);
        $pdo->exec("ANALYZE TABLE {$table}");
        $elapsed = microtime(true) - $start;
        print_success("Tabela '{$table}' analisada (" . format_time($elapsed) . ")");
    } catch (Throwable $e) {
        print_warning("Erro ao analisar '{$table}': " . $e->getMessage());
    }
}

// ==================================================================================
// VERIFICAÇÃO DE PERFORMANCE
// ==================================================================================

print_section("5. Comparativo de Performance (Amostra)");

// Query de teste sem filter
$test_query = "SELECT id, ip, numero_serie FROM equipamentos WHERE tipo_equipamento_id = 1 LIMIT 100";

try {
    $start = microtime(true);
    $result = $pdo->query($test_query);
    $result->fetchAll();
    $elapsed = microtime(true) - $start;
    
    print_info("Query de teste: " . format_time($elapsed));
    if ($elapsed < 0.1) {
        print_success("Performance excelente!");
    } elseif ($elapsed < 0.5) {
        print_success("Performance boa");
    } else {
        print_warning("Considere otimizações adicionais");
    }
} catch (Throwable $e) {
    print_warning("Erro ao testar: " . $e->getMessage());
}

// ==================================================================================
// RECOMENDAÇÕES
// ==================================================================================

print_section("6. Próximos Passos");

print_info("1. Implementar Cache Layer (Redis) para modelos, locais, dashboard");
print_info("2. Refatorar queries N+1 em api_cameras.php e api_alarmes.php");
print_info("3. Implementar Query Logger para monitorar queries lentas");
print_info("4. Setup Connection Pooling no MySQL");
print_info("5. Rodar load testing para validar ganhos");

// ==================================================================================
// DOCUMENTAÇÃO
// ==================================================================================

print_section("7. Informações Adicionais");

print_info("Config MySQL recomendada em my.cnf:");
echo "  [mysqld]\n";
echo "  max_connections = 100\n";
echo "  max_user_connections = 50\n";
echo "  query_cache_type = 1\n";
echo "  query_cache_size = 64M\n";
echo "\n";

print_info("Para ativar slow query log:");
echo "  long_query_time = 2\n";
echo "  log_queries_not_using_indexes = 1\n";
echo "  slow_query_log_file = /var/log/mysql/slow-query.log\n";

// ==================================================================================
// FINALIZAÇÃO
// ==================================================================================

$total_time = microtime(true) - EXECUTION_START;

print_section("✅ Otimização Concluída");

echo COLOR_GREEN . "Tempo total: " . format_time($total_time) . COLOR_RESET . "\n\n";

if ($failed_count === 0) {
    print_success("Todas as operações foram bem-sucedidas!");
    exit(0);
} else {
    print_warning("Algumas operações falharam. Verifique os erros acima.");
    exit(1);
}
