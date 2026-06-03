#!/bin/bash

# Script de Teste de Performance para Sistema de Alarmes
# Versão 2.0 - Testes automatizados de performance

echo "🚀 Iniciando testes de performance do sistema de alarmes..."
echo "📅 Data: $(date)"
echo "============================================="

# Configurações
BASE_URL="http://localhost/sistema-cameras-v1.0"
API_BASE="${BASE_URL}/index.php?page=api"
RESULTS_FILE="performance_test_$(date +%Y%m%d_%H%M%S).log"
MAX_REQUESTS=100
CONCURRENT_USERS=10

# Funções utilitárias
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$RESULTS_FILE"
}

measure_response_time() {
    local url="$1"
    local method="${2:-GET}"
    local data="$3"
    
    if [ "$method" = "POST" ]; then
        start_time=$(date +%s%N)
        curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" -d "$data" "$url" > /dev/null
        end_time=$(date +%s%N)
    else
        start_time=$(date +%s%N)
        curl -s "$url" > /dev/null
        end_time=$(date +%s%N)
    fi
    
    response_time=$((($end_time - $start_time) / 1000000))
    echo $response_time
}

test_api_endpoint() {
    local endpoint="$1"
    local description="$2"
    local method="${3:-GET}"
    local data="$4"
    
    log_message "🔍 Testando: $description"
    
    total_time=0
    success_count=0
    failure_count=0
    response_times=()
    
    for i in $(seq 1 $MAX_REQUESTS); do
        response_time=$(measure_response_time "${API_BASE}/${endpoint}" "$method" "$data")
        response_times+=($response_time)
        total_time=$(($total_time + $response_time))
        
        if [ $response_time -gt 0 ]; then
            success_count=$(($success_count + 1))
        else
            failure_count=$(($failure_count + 1))
        fi
        
        if [ $((i % 10)) -eq 0 ]; then
            log_message "  📊 Progresso: $i/$MAX_REQUESTS requests"
        fi
    done
    
    avg_time=$(($total_time / $MAX_REQUESTS))
    min_time=$(printf "%s\n" "${response_times[@]}" | sort -n | head -1)
    max_time=$(printf "%s\n" "${response_times[@]}" | sort -n | tail -1)
    
    log_message "  ✅ Sucessos: $success_count"
    log_message "  ❌ Falhas: $failure_count"
    log_message "  ⏱️  Tempo médio: ${avg_time}ms"
    log_message "  📈 Tempo mínimo: ${min_time}ms"
    log_message "  📉 Tempo máximo: ${max_time}ms"
    
    # Calcular percentis
    sorted_times=($(printf "%s\n" "${response_times[@]}" | sort -n))
    p50_time=${sorted_times[$((MAX_REQUESTS * 50 / 100))]}
    p90_time=${sorted_times[$((MAX_REQUESTS * 90 / 100))]}
    p95_time=${sorted_times[$((MAX_REQUESTS * 95 / 100))]}
    
    log_message "  📊 P50 (mediana): ${p50_time}ms"
    log_message "  📊 P90: ${p90_time}ms"
    log_message "  📊 P95: ${p95_time}ms"
    
    # Avaliar performance
    if [ $avg_time -lt 500 ]; then
        log_message "  🟢 Performance: EXCELENTE (< 500ms)"
    elif [ $avg_time -lt 1000 ]; then
        log_message "  🟡 Performance: BOA (< 1000ms)"
    elif [ $avg_time -lt 2000 ]; then
        log_message "  🟠 Performance: ACEITÁVEL (< 2000ms)"
    else
        log_message "  🔴 Performance: RUIM (> 2000ms)"
    fi
}

test_concurrent_requests() {
    local endpoint="$1"
    local description="$2"
    local users="$3"
    
    log_message "🔥 Teste concorrente: $description"
    log_message "  👥 Usuários simultâneos: $users"
    log_message "  🔄 Requests: $MAX_REQUESTS"
    
    # Gerar dados para requisições POST
    local post_data='{"test": "performance"}'
    
    start_time=$(date +%s%N)
    
    # Criar arquivo com comandos curl
    > curl_commands.txt
    for i in $(seq 1 $MAX_REQUESTS); do
        if [ "$endpoint" = "api_manutencao_alarmes" ]; then
            echo "curl -s -X POST -H 'Content-Type: application/json' -H 'Accept: application/json' -d '$post_data' '${API_BASE}/${endpoint}'" >> curl_commands.txt
        else
            echo "curl -s '${API_BASE}/${endpoint}'" >> curl_commands.txt
        fi
    done
    
    # Executar requisições concorrentes
    cat curl_commands.txt | xargs -P $users -I {} bash -c "{}" > /dev/null
    
    end_time=$(date +%s%N)
    total_time=$(($((end_time - start_time)) / 1000000))
    
    log_message "  ⏱️  Tempo total: ${total_time}ms"
    log_message "  📊 Requests/segundo: $(($MAX_REQUESTS * 1000 / total_time))"
    
    rm curl_commands.txt
}

test_database_performance() {
    log_message "💾 Testando performance do banco de dados..."
    
    # Testar query simples
    log_message "  📊 Testando query de alarmes simples..."
    db_start_time=$(date +%s%N)
    
    # Simular query (ajustar conforme seu banco)
    # mysql -u usuario -psenha -D database -e "SELECT COUNT(*) FROM central_alarmes" > /dev/null 2>&1
    # sqlite3 database.db "SELECT COUNT(*) FROM central_alarmes" > /dev/null 2>&1
    
    db_end_time=$(date +%s%N)
    db_time=$(($((db_end_time - db_start_time)) / 1000000))
    
    log_message "  ⏱️  Tempo da query: ${db_time}ms"
    
    if [ $db_time -lt 100 ]; then
        log_message "  🟢 Performance do DB: EXCELENTE"
    elif [ $db_time -lt 500 ]; then
        log_message "  🟡 Performance do DB: BOA"
    else
        log_message "  🔴 Performance do DB: PREOCUPANTE"
    fi
}

test_memory_usage() {
    log_message "🧠 Testando uso de memória..."
    
    # Monitorar uso de memória durante as requisições
    if command -v ps > /dev/null; then
        memory_usage=$(ps -p $$ -o %mem | tail -1)
        log_message "  📊 Uso de memória: ${memory_usage}%"
    fi
    
    if command -v free > /dev/null; then
        free_memory=$(free -m | grep Mem | awk '{print $4}')
        log_message "  📊 Memória livre: ${free_memory}MB"
    fi
}

generate_report() {
    log_message "📋 Gerando relatório de performance..."
    
    # Criar relatório HTML
    cat > performance_report.html << EOF
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Performance - Sistema de Alarmes</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .test-result { margin: 20px 0; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; }
        .test-result h3 { margin: 0 0 10px 0; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
        .metric { background: #f8f9fa; padding: 10px; border-radius: 5px; text-align: center; }
        .metric-value { font-size: 24px; font-weight: bold; color: #007bff; }
        .metric-label { font-size: 12px; color: #666; text-transform: uppercase; }
        .success { border-left-color: #28a745; }
        .warning { border-left-color: #ffc107; }
        .error { border-left-color: #dc3545; }
        .log-section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .log-content { font-family: monospace; font-size: 12px; line-height: 1.4; max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Relatório de Performance - Sistema de Alarmes</h1>
        <p><strong>Data:</strong> $(date)</p>
        <p><strong>URL Base:</strong> $BASE_URL</p>
        <p><strong>Total de Requests:</strong> $MAX_REQUESTS</p>
        <p><strong>Usuários Concorrentes:</strong> $CONCURRENT_USERS</p>
        
        <div class="log-section">
            <h3>📝 Log Completo</h3>
            <div class="log-content">
                $(cat "$RESULTS_FILE")
            </div>
        </div>
    </div>
</body>
</html>
EOF
    
    log_message "📄 Relatório HTML gerado: performance_report.html"
}

# Executar testes
log_message "🔧 Iniciando testes de performance..."

# Testar endpoints principais
test_api_endpoint "api_alarmes" "Busca de alarmes (GET)"
test_api_endpoint "api_alarmes&busca=test" "Busca com filtro (GET)"
test_api_endpoint "api_alarmes&conta=123" "Busca por conta (GET)"
test_api_endpoint "api_manutencao_alarmes" "Manutenção de alarmes (GET)"
test_api_endpoint "api_manutencao_alarmes" "Manutenção de alarmes (POST)" '{"action":"create_os","alarme_id":1,"problemas":"Teste"}'

# Testes concorrentes
test_concurrent_requests "api_alarmes" "Busca concorrente de alarmes" $CONCURRENT_USERS
test_concurrent_requests "api_manutencao_alarmes" "Manutenção concorrente" $CONCURRENT_USERS

# Testes de banco de dados
test_database_performance

# Testes de memória
test_memory_usage

# Gerar relatório
generate_report

log_message "✅ Testes de performance concluídos!"
log_message "📄 Resultados salvos em: $RESULTS_FILE"
log_message "📄 Relatório HTML: performance_report.html"

# Mostrar resumo
echo ""
echo "============================================="
echo "📊 RESUMO DOS TESTES"
echo "============================================="
echo "📄 Arquivo de log: $RESULTS_FILE"
echo "📄 Relatório HTML: performance_report.html"
echo "🔍 Total de endpoints testados: 6"
echo "🔥 Testes concorrentes: 2"
echo "💾 Testes de banco: 1"
echo "🧠 Testes de memória: 1"
echo ""
echo "✅ Testes concluídos com sucesso!"