#!/bin/bash

# ============================================================================
# Sistema de Câmeras e Alarmes - Script de Deployment Automático
# ============================================================================
# Uso: bash deploy.sh
# Requer: Docker, Docker Compose, bash
# ============================================================================

set -euo pipefail

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Funções de logging
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_ok() {
    echo -e "${GREEN}[OK]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# ============================================================================
# Validações Iniciais
# ============================================================================

validate_environment() {
    log_info "Validando ambiente..."
    
    # Verificar Docker
    if ! command -v docker &> /dev/null; then
        log_error "Docker não encontrado. Instale Docker 24+."
        exit 1
    fi
    
    # Verificar Docker Compose
    if ! command -v docker &> /dev/null || ! docker compose version &>/dev/null; then
        log_error "Docker Compose não encontrado. Instale Docker Compose v2+."
        exit 1
    fi
    
    # Verificar versões
    DOCKER_VERSION=$(docker --version | grep -oP '\d+(?=\.)' | head -1)
    if [[ "$DOCKER_VERSION" -lt 24 ]]; then
        log_warn "Docker versão $DOCKER_VERSION detectada. Recomendado: 24+"
    fi
    
    log_ok "Docker e Docker Compose validados."
}

validate_files() {
    log_info "Validando arquivos necessários..."
    
    local required_files=(
        "Dockerfile"
        "docker-compose.yml"
        ".dockerignore"
        ".env.example"
    )
    
    for file in "${required_files[@]}"; do
        if [[ ! -f "$file" ]]; then
            log_error "Arquivo necessário não encontrado: $file"
            exit 1
        fi
    done
    
    log_ok "Todos os arquivos necessários encontrados."
}

# ============================================================================
# Preparação
# ============================================================================

prepare_environment() {
    log_info "Preparando ambiente..."
    
    # Verificar se .env existe
    if [[ ! -f ".env" ]]; then
        log_info ".env não encontrado. Gerando com senhas fortes automáticas..."

        DB_PASS=$(openssl rand -base64 32)
        MYSQL_ROOT_PASS=$(openssl rand -base64 32)

        cat > .env <<EOF
CAMERAS_ENV=production
DB_HOST=db
DB_NAME=cftv_gml
DB_USER=cftv_user
DB_PASS=$DB_PASS
MYSQL_ROOT_PASS=$MYSQL_ROOT_PASS
APP_TIMEZONE=America/Sao_Paulo
CAMERAS_SESSION_TIMEOUT=3600
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800
CAMERAS_CSP_ALLOW_INLINE_STYLES=0
APP_PORT=80
EOF

        chmod 600 .env
        log_ok ".env criado com senhas seguras (32 caracteres randomicos)."
        log_warn "ANOTE AS SENHAS GERADAS:"
        log_warn "  DB_PASS=$DB_PASS"
        log_warn "  MYSQL_ROOT_PASS=$MYSQL_ROOT_PASS"
        log_warn "Salve em um cofre de senhas (KeePass, 1Password, etc.)"
    fi
    
    # Verificar permissões do .env
    local perms=$(stat -c '%a' .env 2>/dev/null || stat -f '%OLp' .env 2>/dev/null | tail -c 3)
    if [[ "$perms" != "600" ]]; then
        log_warn "Corrigindo permissões do .env para 600..."
        chmod 600 .env
    fi
    
    log_ok "Ambiente preparado."
}

# ============================================================================
# Build
# ============================================================================

build_image() {
    log_info "Compilando imagem Docker..."
    
    if docker compose build; then
        log_ok "Imagem compilada com sucesso."
    else
        log_error "Falha ao compilar imagem."
        exit 1
    fi
}

# ============================================================================
# Deploy
# ============================================================================

deploy_containers() {
    log_info "Iniciando containers..."
    
    if docker compose up -d; then
        log_ok "Containers iniciados."
    else
        log_error "Falha ao iniciar containers."
        exit 1
    fi
    
    # Aguardar healthchecks
    log_info "Aguardando healthchecks (até 60s)..."
    local max_attempts=60
    local attempt=0
    
    while [[ $attempt -lt $max_attempts ]]; do
        local db_health=$(docker inspect --format='{{.State.Health.Status}}' cameras-db 2>/dev/null || echo "unknown")
        local app_health=$(docker inspect --format='{{.State.Health.Status}}' cameras-app 2>/dev/null || echo "unknown")
        
        if [[ "$db_health" == "healthy" && "$app_health" == "healthy" ]]; then
            log_ok "Todos os containers estão healthy."
            return 0
        fi
        
        if [[ $((attempt % 5)) -eq 0 ]]; then
            log_info "  DB: $db_health | APP: $app_health (tentativa $((attempt+1))/$max_attempts)"
        fi
        
        sleep 1
        ((attempt++))
    done
    
    log_warn "Timeout aguardando healthchecks. Verificando logs..."
    docker compose logs
    return 1
}

# ============================================================================
# Validação Pós-Deploy
# ============================================================================

validate_deployment() {
    log_info "Validando deployment..."
    
    # Testar HTTP
    log_info "Testando endpoint health..."
    local http_code=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/index.php?page=api/api_health 2>/dev/null || echo "000")
    
    if [[ "$http_code" =~ ^(200|401)$ ]]; then
        log_ok "HTTP respondeu com status $http_code"
    else
        log_error "HTTP respondeu com status $http_code. Verifique logs com: docker compose logs app"
        return 1
    fi
    
    # Testar acesso ao banco
    log_info "Testando conexão com banco..."
    if docker exec cameras-app php -r "require 'config/config.php'; new database();" 2>/dev/null; then
        log_ok "Conexão com banco validada."
    else
        log_error "Falha ao conectar com banco. Verifique logs."
        return 1
    fi
    
    log_ok "Deployment validado com sucesso!"
}

# ============================================================================
# Informações Finais
# ============================================================================

print_summary() {
    echo ""
    echo "=========================================================================="
    echo "                   ✅ DEPLOYMENT CONCLUÍDO COM SUCESSO!"
    echo "=========================================================================="
    echo ""
    echo "🌐 URL da Aplicação:"
    echo "   http://localhost/index.php?page=login"
    echo ""
    echo "👥 Usuários padrão (trocar senha no primeiro login):"
    echo "   - admin / temporária"
    echo "   - supervisor / temporária"
    echo "   - user / temporária"
    echo ""
    echo "📋 Comandos úteis:"
    echo "   Ver status:      docker compose ps"
    echo "   Ver logs:        docker compose logs -f app"
    echo "   Entrar no bash:  docker exec -it cameras-app bash"
    echo "   Acessar MySQL:   docker exec -it cameras-db mysql -u root -p"
    echo ""
    echo "📖 Documentação:"
    echo "   Guia completo:   cat DEPLOY.md"
    echo "   Config exemplo:  cat .env.example"
    echo ""
    echo "=========================================================================="
    echo ""
}

# ============================================================================
# Main
# ============================================================================

main() {
    echo ""
    echo "=========================================================================="
    echo "   Sistema de Câmeras e Alarmes - Docker Deploy"
    echo "=========================================================================="
    echo ""
    
    validate_environment
    validate_files
    prepare_environment
    build_image
    deploy_containers || exit 1
    validate_deployment || exit 1
    print_summary
}

# Executar
main "$@"
