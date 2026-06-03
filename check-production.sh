#!/bin/bash

# ============================================================================
# Verificação Pós-Deploy - Sistema de Câmeras
# Uso: bash check-production.sh
# ============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

check_status() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}STATUS DOS CONTAINERS${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    docker compose ps
    echo ""
}

check_healthchecks() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}HEALTHCHECKS${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    local db_health=$(docker inspect --format='{{.State.Health.Status}}' cameras-db 2>/dev/null || echo "unknown")
    local app_health=$(docker inspect --format='{{.State.Health.Status}}' cameras-app 2>/dev/null || echo "unknown")
    
    if [[ "$db_health" == "healthy" ]]; then
        echo -e "${GREEN}✓${NC} MySQL: $db_health"
    else
        echo -e "${RED}✗${NC} MySQL: $db_health"
    fi
    
    if [[ "$app_health" == "healthy" ]]; then
        echo -e "${GREEN}✓${NC} APP: $app_health"
    else
        echo -e "${RED}✗${NC} APP: $app_health"
    fi
    echo ""
}

check_http() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}TESTE HTTP${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    local code=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/index.php?page=api/api_health 2>/dev/null || echo "000")
    
    if [[ "$code" =~ ^(200|401)$ ]]; then
        echo -e "${GREEN}✓${NC} HTTP Status: $code"
    else
        echo -e "${RED}✗${NC} HTTP Status: $code"
    fi
    echo ""
}

check_database() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}CONEXÃO COM BANCO${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    if docker exec cameras-app php -r "require 'config/config.php'; new database(); echo 'OK';" 2>/dev/null | grep -q "OK"; then
        echo -e "${GREEN}✓${NC} Conexão com banco OK"
    else
        echo -e "${RED}✗${NC} Falha na conexão com banco"
    fi
    echo ""
}

check_tables() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}TABELAS CRÍTICAS${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    local tables=("usuarios" "auditoria_eventos" "login_attempts" "user_sessions" "equipamentos_camera" "central_alarmes")
    
    for table in "${tables[@]}"; do
        if docker exec cameras-db mysql -u cftv_user -pcftv_gml cftv_gml -e "SELECT 1 FROM $table LIMIT 1;" &>/dev/null; then
            local count=$(docker exec cameras-db mysql -u cftv_user -pcftv_gml cftv_gml -se "SELECT COUNT(*) FROM $table;")
            echo -e "${GREEN}✓${NC} $table ($count registros)"
        else
            echo -e "${RED}✗${NC} $table (não encontrada)"
        fi
    done
    echo ""
}

check_logs() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}ÚLTIMAS LINHAS DE LOG (últimas 10)${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    docker compose logs app --tail 10
    echo ""
}

check_disk_space() {
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}ESPAÇO EM DISCO${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    df -h / | tail -1 | awk '{print "Uso: " $5 " (Disponível: " $4 ")"}'
    
    # Docker images
    local docker_size=$(docker system df --format='{{.Size}}' 2>/dev/null | head -1 || echo "N/A")
    echo "Tamanho Docker: $docker_size"
    echo ""
}

main() {
    echo ""
    echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  VERIFICAÇÃO PÓS-DEPLOY - SISTEMA DE CÂMERAS${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
    echo ""
    
    check_status
    check_healthchecks
    check_http
    check_database
    check_tables
    check_logs
    check_disk_space
    
    echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}Verificação completa!${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════════${NC}"
    echo ""
}

main "$@"
