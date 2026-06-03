# Instalação — Sistema de Câmeras e Alarmes

## 📋 Pré-requisitos

- Ubuntu Server 22.04+ ou Debian 12+
- Docker Engine 24+ e Docker Compose v2
- Git (opcional, para clonar o repositório)

## 🚀 Instalação Rápida

```bash
# 1. Clonar o projeto
git clone <seu-repositorio> sistema-cameras
cd sistema-cameras

# 2. Executar instalador
bash install.sh
```

O instalador irá:
1. Verificar Docker + Docker Compose
2. Criar arquivo `.env` com senhas seguras
3. Buildar a imagem PHP/Apache
4. Subir os containers (app + mysql)
5. Aguardar healthchecks
6. Executar smoke test

Acessar: **http://localhost/**

---

## 🔧 Instalação Manual Passo a Passo

### 1. Verificar pré-requisitos

```bash
docker --version        # Docker 24+
docker compose version  # Compose v2
```

Se não estiverem instalados:

```bash
# Ubuntu / Debian
sudo apt update
sudo apt install -y docker.io docker-compose-v2
sudo systemctl enable --now docker
```

### 2. Preparar arquivos

```bash
# Criar diretório da aplicação
sudo mkdir -p /opt/sistema-cameras
sudo chown $USER:$USER /opt/sistema-cameras

# Copiar projeto (ou clonar do git)
cp -r /caminho/sistema-cameras-v1.0/* /opt/sistema-cameras/
```

### 3. Configurar variáveis de ambiente

```bash
cd /opt/sistema-cameras

# Opção A: definir senhas manualmente
export DB_PASS="sua_senha_forte_aqui"
export MYSQL_ROOT_PASS="root_senha_forte_aqui"

# Opção B: gerar senhas automáticas
export DB_PASS=$(openssl rand -base64 24)
export MYSQL_ROOT_PASS=$(openssl rand -base64 32)
```

### 4. Construir e iniciar

```bash
docker compose build
docker compose up -d
```

### 5. Verificar healthcheck

```bash
# Aguardar MySQL ficar pronto
watch -n 2 docker inspect --format='{{.State.Health.Status}}' cameras-db

# Após MySQL healthy, aguardar app
watch -n 2 docker inspect --format='{{.State.Health.Status}}' cameras-app
```

### 6. Smoke test

```bash
curl -s -o /dev/null -w "%{http_code}" \
  http://localhost/index.php?page=api/api_health
# Esperado: 401 (sem sessão = autenticação necessária)
```

---

## ⚙️ Variáveis de Ambiente

| Variável | Obrigatória | Padrão | Descrição |
|----------|-------------|--------|-----------|
| `DB_USER` | Não | `cftv_user` | Usuário do MySQL |
| `DB_PASS` | Não (autogerada) | — | Senha do MySQL |
| `MYSQL_ROOT_PASS` | Não (autogerada) | — | Senha root do MySQL |
| `APP_PORT` | Não | `80` | Porta do servidor web no host |
| `APP_TIMEZONE` | Não | `America/Sao_Paulo` | Fuso horário |

---

## 🐳 Arquitetura dos Containers

```
┌──────────────────────────────────────────────┐
│                  docker-compose               │
│                                               │
│  ┌─────────────────────┐  ┌────────────────┐ │
│  │     cameras-app     │  │   cameras-db   │ │
│  │  php:8.1-apache     │  │  mysql:8.0     │ │
│  │  :80                │  │  :3306         │ │
│  │                     │  │                │ │
│  │  healthcheck:       │  │  healthcheck:  │ │
│  │  GET /api_health    │  │  mysqladmin    │ │
│  └──────────┬──────────┘  └───────┬────────┘ │
│             │                     │          │
│             └─────────network─────┘          │
└──────────────────────────────────────────────┘
```

- MySQL **não tem porta exposta** para o host (acesso apenas via rede interna)
- Dados do MySQL armazenados em volume Docker (`mysql_data`)
- Schema SQL importado automaticamente na primeira inicialização
- Event scheduler ativado para limpeza automática de logs

---

## 🛠️ Comandos do Dia a Dia

```bash
# Ver logs
docker compose logs -f
docker compose logs -f app
docker compose logs -f db

# Parar containers
docker compose down

# Parar e remover dados do banco (cuidado!)
docker compose down -v

# Reiniciar
docker compose restart

# Atualizar após mudanças no código
docker compose up -d --build

# Acessar o container app
docker exec -it cameras-app bash

# Acessar o MySQL
docker exec -it cameras-db mysql -u root -p
```

---

## 🔒 Segurança

### Proteções implementadas

| Medida | Como |
|--------|------|
| CSP (Content Security Policy) | Headers com nonces para scripts |
| CSRF | Token em todo POST |
| XSS | `htmlspecialchars()` em outputs |
| SQL Injection | Prepared statements (PDO) |
| Sessão | Timeout inatividade + absoluto, sessão única |
| Senhas | bcrypt, política 8+ caracteres |
| Auditoria | Triggers no banco para INSERT/UPDATE/DELETE |
| Rate limit | 5 tentativas de login / 15 min |
| Erros | `display_errors=Off`, exceptions logadas |

### Rede isolada

O MySQL **não é acessível** externamente. Apenas o container `cameras-app` consegue se conectar via rede interna `internal`.

### Usuários padrão

| Usuário | Senha | Perfil | Obriga trocar senha? |
|---------|-------|--------|---------------------|
| `admin` | temporária | Administrador | Sim |
| `supervisor` | temporária | Supervisor | Sim |
| `user` | temporária | Usuário | Sim |

**Altere todas as senhas temporárias no primeiro login.**

---

## 🔄 Backup e Restauração

### Backup do banco

```bash
docker exec cameras-db mysqldump \
  --single-transaction \
  --routines \
  --events \
  -u root -p"$MYSQL_ROOT_PASS" \
  cftv_gml > backup_$(date +%Y%m%d_%H%M%S).sql
```

### Restauração

```bash
cat backup.sql | docker exec -i cameras-db \
  mysql -u root -p"$MYSQL_ROOT_PASS" cftv_gml
```

---

## 📊 Monitoramento

### Healthcheck automático

O Docker reinicia containers automaticamente se falharem (`restart: unless-stopped`).

### Logs

```bash
# Apache access log
docker exec cameras-app tail -f /var/log/apache2/access.log

# Apache error log
docker exec cameras-app tail -f /var/log/apache2/error.log

# PHP error log
docker exec cameras-app tail -f /var/log/apache2/error.log
```

---

## 🧪 Smoke Tests

```bash
# Teste de health
curl -I http://localhost/index.php?page=api/api_health

# Teste de login (deve redirecionar)
curl -I http://localhost/index.php?page=login

# Teste de 404
curl -I http://localhost/index.php?page=inexistente
```

---

## ⚠️ Solução de Problemas

| Problema | Causa provável | Solução |
|----------|---------------|---------|
| Container não inicia | Porta 80 ocupada | `APP_PORT=8080` ou pare outro serviço |
| MySQL não fica healthy | Init SQL corrompido | `docker compose logs db` |
| App não fica healthy | DB_HOST incorreto | Verificar variáveis de ambiente |
| Página 500 | Erro PHP | `docker compose logs app` |
| CSP bloqueia estilo | Bootstrap precisa inline | `CAMERAS_CSP_ALLOW_INLINE_STYLES=1` |
