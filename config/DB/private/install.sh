#!/usr/bin/env bash
# Instalacao automatizada - sistema-cameras-v1.0 (Ubuntu/Debian)
# Execute com: sudo bash config/DB/install.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# =========================
# CONFIGURACAO (override por variavel de ambiente)
# =========================
APP_NAME="${APP_NAME:-sistema-cameras-v1.0}"
APP_DIR="${APP_DIR:-/var/www/${APP_NAME}}"
APP_PORT="${APP_PORT:-80}"
SERVER_NAME="${SERVER_NAME:-localhost}"
APP_TIMEZONE="${APP_TIMEZONE:-America/Sao_Paulo}"

DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-cftv_gml}"
DB_USER="${DB_USER:-gmluser}"
DB_PASS="${DB_PASS:-}"

SQL_FILE_SOURCE="${SQL_FILE_SOURCE:-${SCRIPT_DIR}/cftv_gml.sql}"

# 1 = recria o banco e importa SQL completo como root (permite DROP/CREATE)
# 0 = importa de forma segura, removendo comandos administrativos do dump
RESET_DB_ON_IMPORT="${RESET_DB_ON_IMPORT:-0}"

# 1 = copia codigo de PROJECT_ROOT para APP_DIR (modo deploy)
# 0 = usa codigo no caminho atual sem copiar
COPY_PROJECT_TO_APP_DIR="${COPY_PROJECT_TO_APP_DIR:-1}"

if [[ -z "${DB_PASS}" ]]; then
    DB_PASS="$(openssl rand -base64 32)"
    echo "DB_PASS não definido. Senha gerada automaticamente: ${DB_PASS}"
    echo "ANOTE ESTA SENHA PARA ACESSO FUTURO AO BANCO."
fi

MYSQL_ROOT_PASS="${MYSQL_ROOT_PASS:-$(openssl rand -base64 32)}"

# =========================
# FUNCOES
# =========================
print_info() {
    echo -e "\033[1;34m[INFO]\033[0m $1"
}

print_ok() {
    echo -e "\033[1;32m[OK]\033[0m $1"
}

print_warn() {
    echo -e "\033[1;33m[AVISO]\033[0m $1"
}

print_error() {
    echo -e "\033[1;31m[ERRO]\033[0m $1"
}

require_root() {
    if [[ "${EUID}" -ne 0 ]]; then
        print_error "Execute como root: sudo bash config/DB/install.sh"
        exit 1
    fi
}

install_packages() {
    print_info "[1/10] Instalando pacotes..."
    apt update
    apt -y install apache2 mariadb-server php php-cli php-mysql php-mbstring php-xml php-curl php-gd php-zip unzip rsync curl
    print_ok "Pacotes instalados."
}

prepare_app_files() {
    print_info "[2/10] Preparando arquivos da aplicacao..."
    mkdir -p "${APP_DIR}"

    if [[ "${COPY_PROJECT_TO_APP_DIR}" == "1" ]]; then
        rsync -a --delete \
            --exclude ".git/" \
            --exclude ".github/" \
            --exclude "node_modules/" \
            --exclude "vendor/" \
            "${PROJECT_ROOT}/" "${APP_DIR}/"
        print_ok "Projeto copiado para ${APP_DIR}"
    else
        print_warn "COPY_PROJECT_TO_APP_DIR=0: usando arquivos no caminho atual (${PROJECT_ROOT})"
        APP_DIR="${PROJECT_ROOT}"
    fi

    # Permissoes seguras padrao (somente leitura para codigo)
    chown -R root:www-data "${APP_DIR}"
    find "${APP_DIR}" -type d -exec chmod 755 {} \;
    find "${APP_DIR}" -type f -exec chmod 644 {} \;

    # Pasta opcional para uploads/cache de escrita
    mkdir -p "${APP_DIR}/public/uploads"
    chown -R www-data:www-data "${APP_DIR}/public/uploads"
    chmod -R 775 "${APP_DIR}/public/uploads"
}

configure_services() {
    print_info "[3/10] Ativando servicos..."
    systemctl enable --now mariadb
    systemctl enable --now apache2
    print_ok "Apache e MariaDB ativos."
}

configure_php() {
    print_info "[4/10] Ajustando PHP..."
    local php_ini
    php_ini="$(ls -1 /etc/php/*/apache2/php.ini 2>/dev/null | sort -V | tail -n1 || true)"

    if [[ -z "${php_ini}" || ! -f "${php_ini}" ]]; then
        print_warn "php.ini do Apache nao encontrado. Pulando ajustes de php.ini."
        return
    fi

    sed -i "s#^;*date.timezone *=.*#date.timezone = ${APP_TIMEZONE}#g" "${php_ini}" || true
    sed -i "s#^display_errors *=.*#display_errors = Off#g" "${php_ini}" || true
    sed -i "s#^log_errors *=.*#log_errors = On#g" "${php_ini}" || true
    sed -i "s#^memory_limit *=.*#memory_limit = 256M#g" "${php_ini}" || true
    sed -i "s#^upload_max_filesize *=.*#upload_max_filesize = 20M#g" "${php_ini}" || true
    sed -i "s#^post_max_size *=.*#post_max_size = 24M#g" "${php_ini}" || true
    sed -i "s#^session.cookie_httponly *=.*#session.cookie_httponly = 1#g" "${php_ini}" || true

    print_ok "php.ini ajustado: ${php_ini}"
}

configure_apache() {
    print_info "[5/10] Configurando Apache..."

    if [[ "${APP_PORT}" != "80" ]]; then
        if ! grep -q "Listen ${APP_PORT}" /etc/apache2/ports.conf; then
            echo "Listen ${APP_PORT}" >> /etc/apache2/ports.conf
        fi
    fi

    cat > /etc/apache2/sites-available/${APP_NAME}.conf <<EOF
<VirtualHost *:${APP_PORT}>
    ServerName ${SERVER_NAME}
    DocumentRoot ${APP_DIR}/public

    SetEnv DB_HOST "${DB_HOST}"
    SetEnv DB_NAME "${DB_NAME}"
    SetEnv DB_USER "${DB_USER}"
    SetEnv DB_PASS "${DB_PASS}"
    SetEnv APP_TIMEZONE "${APP_TIMEZONE}"

    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${APP_NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${APP_NAME}_access.log combined
</VirtualHost>
EOF

    a2enmod rewrite >/dev/null
    a2ensite "${APP_NAME}.conf" >/dev/null
    a2dissite 000-default.conf >/dev/null || true

    # .htaccess minimo para front controller em public/index.php
    if [[ ! -f "${APP_DIR}/public/.htaccess" ]]; then
        cat > "${APP_DIR}/public/.htaccess" <<'EOF'
AddDefaultCharset UTF-8
Options -Indexes

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^(.*)$ index.php?page=$1 [QSA,L]
</IfModule>
EOF
        print_ok "public/.htaccess criado."
    else
        print_info "public/.htaccess existente mantido."
    fi

    apache2ctl configtest
    systemctl restart apache2
    print_ok "Apache configurado."
}

configure_database() {
    print_info "[6/10] Configurando banco (${DB_NAME})..."

    mysql <<EOF
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
EOF

    print_ok "Banco e usuario configurados."
}

import_sql() {
    print_info "[7/10] Importando SQL..."

    if [[ ! -f "${SQL_FILE_SOURCE}" ]]; then
        print_error "Arquivo SQL nao encontrado: ${SQL_FILE_SOURCE}"
        exit 1
    fi

    if [[ "${RESET_DB_ON_IMPORT}" == "1" ]]; then
        print_warn "RESET_DB_ON_IMPORT=1: importacao completa com comandos administrativos."
        mysql < "${SQL_FILE_SOURCE}"
        print_ok "Importacao completa executada."
        return
    fi

    # Modo seguro: remove comandos de admin do dump e importa apenas schema/dados.
    local tmp_sql
    tmp_sql="$(mktemp)"
    sed -E \
      '/^[[:space:]]*(DROP[[:space:]]+DATABASE|CREATE[[:space:]]+DATABASE|DROP[[:space:]]+USER|CREATE[[:space:]]+USER|GRANT[[:space:]]|FLUSH[[:space:]]+PRIVILEGES|SHOW[[:space:]]+GRANTS|USE[[:space:]]+)/Id' \
      "${SQL_FILE_SOURCE}" > "${tmp_sql}"

    mysql --default-character-set=utf8mb4 -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${tmp_sql}"
    rm -f "${tmp_sql}"
    print_ok "Importacao segura concluida."
}

post_checks() {
    print_info "[8/10] Verificando servicos e banco..."

    systemctl is-active --quiet apache2 && print_ok "Apache ativo." || { print_error "Apache inativo."; exit 1; }
    systemctl is-active --quiet mariadb && print_ok "MariaDB ativo." || { print_error "MariaDB inativo."; exit 1; }

    mysql -u "${DB_USER}" -p"${DB_PASS}" -e "USE ${DB_NAME}; SHOW TABLES;" >/dev/null
    print_ok "Conexao com banco validada."
}

smoke_test() {
    print_info "[9/10] Smoke test HTTP local..."
    local url
    if [[ "${APP_PORT}" == "80" ]]; then
        url="http://127.0.0.1/"
    else
        url="http://127.0.0.1:${APP_PORT}/"
    fi

    local code
    code="$(curl -s -o /dev/null -w "%{http_code}" "${url}")"
    if [[ "${code}" =~ ^(200|302)$ ]]; then
        print_ok "HTTP local respondeu ${code} (${url})"
    else
        print_warn "HTTP local respondeu ${code} (${url}). Verifique logs Apache."
    fi
}

finish_message() {
    print_info "[10/10] Instalacao concluida."
    echo "============================================================"
    echo "App.............: ${APP_NAME}"
    echo "Diretorio.......: ${APP_DIR}"
    echo "Banco...........: ${DB_NAME}"
    echo "Usuario DB......: ${DB_USER}"
    echo "Senha DB........: ${DB_PASS}"
    echo "ServerName......: ${SERVER_NAME}"
    echo "Porta...........: ${APP_PORT}"
    echo "Timezone........: ${APP_TIMEZONE}"
    if [[ "${APP_PORT}" == "80" ]]; then
        echo "URL.............: http://${SERVER_NAME}/"
    else
        echo "URL.............: http://${SERVER_NAME}:${APP_PORT}/"
    fi
    echo "==========================================================="
    echo "⚠  ANOTE A SENHA DO BANCO ACIMA (DB_PASS)"
    echo "============================================================"
    echo "Dica: para reset completo do banco, rode com RESET_DB_ON_IMPORT=1"
    echo "Exemplo: sudo RESET_DB_ON_IMPORT=1 bash config/DB/install.sh"
}

main() {
    require_root
    install_packages
    prepare_app_files
    configure_services
    configure_php
    configure_apache
    configure_database
    import_sql
    post_checks
    smoke_test
    finish_message
}

main "$@"
