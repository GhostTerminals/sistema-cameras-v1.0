# Manual Completo — Sistema de Gerenciamento de Câmeras e Alarmes v1.0

> **Versão:** 1.0.0 • **Última atualização:** Junho/2026

---

## Sumário

1. [Visão Geral](#1-visão-geral)
2. [Instalação](#2-instalação)
   - 2.1 XAMPP (Windows)
   - 2.2 Docker (WSL2 / Linux)
   - 2.3 Servidor PHP embutido (dev rápido)
3. [Configuração](#3-configuração)
   - 3.1 Variáveis de ambiente
   - 3.2 Banco de dados
   - 3.3 Apache / mod_rewrite
4. [Manual do Usuário](#4-manual-do-usuário)
   - 4.1 Acesso ao sistema
   - 4.2 Gerenciamento de câmeras
   - 4.3 Gerenciamento de alarmes
   - 4.4 Relatórios
   - 4.5 Auditoria
5. [API REST v2](#5-api-rest-v2)
   - 5.1 Autenticação
   - 5.2 Endpoints
   - 5.3 Exemplos de uso
6. [Arquitetura do Sistema](#6-arquitetura-do-sistema)
   - 6.1 Estrutura de diretórios
   - 6.2 Fluxo de requisição
   - 6.3 Segurança
7. [Desenvolvimento](#7-desenvolvimento)
   - 7.1 Requisitos
   - 7.2 Testes
   - 7.3 CI/CD
   - 7.4 Adicionar novo endpoint
8. [Troubleshooting](#8-troubleshooting)
9. [Apêndice](#9-apêndice)

---

## 1. Visão Geral

Sistema web para gerenciamento centralizado de câmeras de vigilância e alarmes em ambientes empresariais (intranet).

### Funcionalidades principais

- Cadastro, edição, exclusão e consulta de câmeras IP
- Cadastro, edição, exclusão e consulta de centrais de alarme
- Manutenções programadas e corretivas
- Relatórios gerenciais (filtros por período, local, status)
- Auditoria completa de operações
- Autenticação com níveis de acesso (admin, supervisor, user)
- API RESTful v2 para integração externa
- Rate limiting, CSRF, CSP e proteção contra ataques

### Tecnologias

| Componente | Tecnologia |
|---|---|
| Backend | PHP 8.3 |
| Database | MySQL 8.0 |
| Frontend | JavaScript vanilla + Bootstrap 5 |
| Servidor | Apache (XAMPP) ou Nginx (Docker) |
| Testes | PHPUnit 11 + Vitest 4 |
| CI/CD | GitHub Actions |
| Container | Docker 24+ / Compose v2 |

---

## 2. Instalação

### 2.1 XAMPP (Windows)

#### Pré-requisitos

- XAMPP com PHP 8.1+ instalado em `C:\xampp`
- Apache e MySQL rodando
- Git para Windows instalado

#### Passo a passo

```batch
:: 1. Clonar o repositório dentro do htdocs do XAMPP
cd C:\xampp\htdocs
git clone <URL_DO_REPOSITORIO> sistema-cameras
cd sistema-cameras

:: 2. Executar o configurador XAMPP (Administrador)
setup-xampp.bat
```

O script `setup-xampp.bat` faz automaticamente:

1. **Verifica** se `.env` existe (cria a partir de `.env.example` se necessário)
2. **Checa** extensões PHP obrigatórias: PDO, pdo_mysql, mbstring, openssl
3. **Importa** o schema do banco `config/DB/cftv_gml.sql` para o MySQL
4. **Confirma** que o mod_rewrite do Apache está habilitado

#### Configuração manual (caso o script falhe)

```bash
# 1. Copiar .env
copy .env.example .env

# 2. Editar .env — ajustar DB_HOST, DB_USER, DB_PASS
notepad .env

# 3. Criar banco de dados
C:\xampp\mysql\bin\mysql -u root -p < config\DB\cftv_gml.sql

# 4. Instalar dependências PHP
composer install

# 5. Habilitar mod_rewrite no Apache
#    Descomentar em C:\xampp\apache\conf\httpd.conf:
#    LoadModule rewrite_module modules/mod_rewrite.so

# 6. Reiniciar Apache no XAMPP Control Panel
```

#### Acesso

```
http://localhost/sistema-cameras/public/
```

### 2.2 Docker (WSL2 / Linux)

#### Pré-requisitos

- Docker Engine 24+
- Docker Compose v2
- Git

#### Passo a passo

```bash
# 1. Clonar
git clone <URL_DO_REPOSITORIO> sistema-cameras
cd sistema-cameras

# 2. Configurar ambiente
cp .env.template .env
nano .env    # Ajustar DB_PASS, MYSQL_ROOT_PASS

# 3. Iniciar containers
docker compose up -d

# 4. Acompanhar logs
docker compose logs -f app

# 5. Acessar
# http://localhost:8080
```

#### Comandos úteis

```bash
# Ver status
docker compose ps

# Parar tudo
docker compose down

# Ver logs do banco
docker compose logs db

# Executar comando dentro do container
docker compose exec app php -v
docker compose exec app composer test

# Acessar MySQL
docker compose exec db mysql -ucftv_user -p cftv_gml

# Reconstruir imagem (após alterações no Dockerfile)
docker compose build app
docker compose up -d
```

### 2.3 Servidor PHP embutido (desenvolvimento rápido)

```bash
# Sem Docker, sem Apache — apenas PHP + MySQL local
composer install
cp .env.example .env
# Editar .env: DB_HOST=localhost
mysql -u root -p < config/DB/cftv_gml.sql
php -S 127.0.0.1:8080 -t public/
```

Acesso: `http://127.0.0.1:8080`

---

## 3. Configuração

### 3.1 Variáveis de ambiente

O arquivo `.env` na raiz do projeto define toda a configuração. Veja `.env.example` para a lista completa.

#### Banco de Dados

| Variável | XAMPP | Docker | Descrição |
|---|---|---|---|
| `DB_HOST` | `localhost` | `db` | Host do MySQL |
| `DB_NAME` | `cftv_gml` | `cftv_gml` | Nome do banco |
| `DB_USER` | `root` | `cftv_user` | Usuário MySQL |
| `DB_PASS` | (sua senha) | (sua senha) | Senha MySQL |

> **Importante:** Ao alternar entre XAMPP e Docker, mude apenas `DB_HOST`.

#### Ambiente

| Variável | Valores | Descrição |
|---|---|---|
| `CAMERAS_ENV` | `development`, `testing`, `production` | Modo de operação |
| `APP_TIMEZONE` | `America/Sao_Paulo` | Fuso horário |
| `CAMERAS_SESSION_TIMEOUT` | `3600` (1h) | Timeout de inatividade |
| `CAMERAS_SESSION_ABSOLUTE_TIMEOUT` | `28800` (8h) | Timeout absoluto |
| `CAMERAS_CSP_ALLOW_INLINE_STYLES` | `0` (strict) ou `1` | Política CSP |

#### Rede

| Variável | Descrição |
|---|---|
| `PROXY_TRUSTED_IPS` | IPs de reverse proxy (vazio = sem proxy) |
| `APP_ALLOWED_ORIGINS` | Origens CORS adicionais |
| `APP_PORT` | Porta da aplicação |

### 3.2 Banco de dados

#### Schema

O arquivo `config/DB/cftv_gml.sql` contém:

- Estrutura completa do banco (tabelas, índices, constraints)
- Dados iniciais (níveis de acesso, admin padrão)
- Configuração de charset utf8mb4

#### Tabelas principais

| Tabela | Finalidade |
|---|---|
| `usuarios` | Usuários do sistema |
| `niveis_acesso` | admin, supervisor, user |
| `user_sessions` | Sessões ativas |
| `equipamentos_camera` | Câmeras cadastradas |
| `central_alarmes` | Centrais de alarme |
| `equipamentos_manutencoes` | Manutenções de câmeras |
| `alarmes_manutencoes` | Manutenções de alarmes |
| `auditoria_eventos` | Log de auditoria |
| `login_attempts` | Tentativas de login (rate limit) |

#### Migrations

Execute as migrations manuais em ordem:

```bash
# XAMPP
C:\xampp\mysql\bin\mysql -u root -p cftv_gml < config/DB/migrations/001_*.sql

# Docker
docker compose exec db mysql -ucftv_user -p cftv_gml < config/DB/migrations/001_*.sql
```

### 3.3 Apache / mod_rewrite

Para o funcionamento correto no XAMPP:

1. Abra `C:\xampp\apache\conf\httpd.conf`
2. Descomente: `LoadModule rewrite_module modules/mod_rewrite.so`
3. Certifique-se de que `AllowOverride` está como `All` para o diretório do projeto:

```apache
<Directory "C:/xampp/htdocs/sistema-cameras">
    AllowOverride All
    Require all granted
</Directory>
```

4. Reinicie o Apache

---

## 4. Manual do Usuário

### 4.1 Acesso ao sistema

#### Login

1. Abra o navegador em `http://localhost/sistema-cameras/public/`
2. Informe usuário e senha fornecidos pelo administrador
3. Após o login, o dashboard principal é exibido

#### Níveis de acesso

| Nível | Permissões |
|---|---|
| **admin** | Acesso total: cadastro, edição, exclusão, relatórios, auditoria, usuários |
| **supervisor** | Cadastro e edição, visualiza relatórios |
| **user** | Visualização e consultas |

#### Recuperação de senha

1. Na tela de login, clique "Esqueceu a senha?"
2. Informe o e-mail cadastrado
3. Uma senha temporária será gerada (exibida uma única vez na tela)
4. Faça login com a senha temporária — o sistema solicitará a troca

### 4.2 Gerenciamento de câmeras

#### Listar câmeras

- Menu: **Câmeras > Listar**
- Tabela com todas as câmeras cadastradas
- Colunas: Código, Nome, IP, Local, Status, Ações
- Botão "Recarregar" atualiza a lista via AJAX
- Campos de busca/filtro no topo da página

#### Cadastrar câmera

1. Menu: **Câmeras > Cadastrar**
2. Preencha os campos obrigatórios:
   - Nome do equipamento
   - IP ou URL de acesso
   - Local (secretaria)
   - Marca / Modelo
   - Tipo (câmera, DVR, LPR, totem)
3. Opcionais: coordenadas geográficas (latitude/longitude), observações
4. Clique **Salvar**

#### Editar câmera

- Na lista, clique no ícone de edição (lápis) ao lado da câmera
- Altere os campos desejados
- Clique **Salvar**
- O formulário não é resetado automaticamente (preserva dados não salvos)

#### Excluir câmera

- Na lista, clique no ícone de exclusão (lixeira)
- Confirme a exclusão no diálogo
- A câmera é removida permanentemente

#### Anexos

- Na tela de edição, seção "Anexos"
- Formatos aceitos: JPEG, PNG, PDF, DOC, XLS (máx. 10MB)
- Upload via drag-and-drop ou seletor de arquivos
- Os anexos são autenticados (requerem sessão ativa para download)

#### Manutenções

1. Na lista, clique no ícone de manutenção
2. Informe: tipo (corretiva/preventiva), descrição, data
3. Anexe fotos ou documentos se necessário
4. Clique **Salvar**

### 4.3 Gerenciamento de alarmes

#### Central de alarmes

- Menu: **Alarmes > Centrais**
- Cadastro e edição de centrais de alarme
- Campos: nome, local, número de série, contato da central

#### Manutenções de alarmes

- Registro de manutenções corretivas e preventivas
- Histórico completo por central
- Possibilidade de anexar documentos

### 4.4 Relatórios

#### Relatório de câmeras

- Menu: **Relatórios > Câmeras**
- Filtros por: período, status, local, tipo de equipamento
- Botão "Exportar CSV" com dados completos
- Tabela interativa com ordenação por coluna

#### Relatório de alarmes

- Menu: **Relatórios > Alarmes**
- Filtros similares aos de câmeras
- Exportação para CSV

#### Gráficos

- Dashboard inicial com indicadores:
  - Total de equipamentos
  - Equipamentos por status
  - Manutenções recentes
  - Distribuição por local

### 4.5 Auditoria

- Menu: **Auditoria**
- Log de todas as operações realizadas no sistema
- Campos: usuário, ação, data/hora, detalhes, IP
- Busca por período, usuário ou ação
- Ordenação por data (decrescente)

---

## 5. API REST v2

### 5.1 Autenticação

A API usa autenticação por sessão (cookie `PHPSESSID`). Todos os endpoints (exceto health check) exigem:

1. Sessão ativa (login realizado via navegador)
2. Token CSRF válido no header `X-CSRF-Token`

#### Obter token CSRF

```javascript
// Incluído automaticamente nas páginas via <meta>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
```

### 5.2 Endpoints

#### Health Check

```
GET /index.php?page=api/api_ping
```

Resposta:
```json
{
  "success": true,
  "code": 200,
  "message": "API v2 disponivel",
  "data": { "ping": "pong" },
  "meta": {
    "timestamp": "2026-06-12T10:30:00+00:00",
    "version": "v2"
  }
}
```

#### Câmeras

| Método | Endpoint | Descrição |
|---|---|---|
| GET | `api/api_cameras` | Listar câmeras (paginado) |
| POST | `api/api_cadastrar_cameras` | Criar câmera |
| POST | `api/api_editar_camera` | Editar câmera |
| POST | `api/api_excluir_camera` | Excluir câmera |

Parâmetros de query para listagem:

- `per_page` (int, default 20) — itens por página
- `page` (int, default 1) — página atual

#### Criar câmera (POST)

```json
{
  "nome": "Camera Portaria",
  "ip": "192.168.1.100",
  "local_id": 1,
  "marca_id": 2,
  "modelo_id": 5,
  "tipo": "camera",
  "latitude": -23.5505,
  "longitude": -46.6333
}
```

#### Editar câmera (POST)

```json
{
  "id": 10,
  "nome": "Camera Portaria - Alterada",
  "ip": "192.168.1.101",
  "status": "ativo"
}
```

#### Excluir câmera (POST)

```json
{
  "id": 10
}
```

#### Dashboard

```
GET /index.php?page=api/api_dashboard
```

Retorna dados agregados para o dashboard inicial.

#### Manutenções

```
GET /index.php?page=api/api_manutencao_cameras
```

### 5.3 Exemplos de uso

#### curl (com sessão)

```bash
# 1. Fazer login e capturar cookie
curl -c cookies.txt -X POST \
  -d "usuario=admin&senha=minha_senha" \
  http://localhost:8080/index.php?page=auth/login_submit

# 2. Obter token CSRF da página
curl -b cookies.txt http://localhost:8080/ | grep csrf-token

# 3. Listar câmeras
curl -b cookies.txt \
  -H "X-CSRF-Token: <TOKEN>" \
  http://localhost:8080/index.php?page=api/api_cameras

# 4. Health check (sem autenticação)
curl http://localhost:8080/index.php?page=api/api_ping
```

#### JavaScript (fetch)

```javascript
async function listarCameras() {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const res = await fetch('/index.php?page=api/api_cameras', {
    headers: { 'X-CSRF-Token': csrf }
  });
  return res.json();
}
```

### Formato de resposta padrão

```json
{
  "success": true,
  "code": 200,
  "message": "Operacao realizada com sucesso",
  "data": { /* dados específicos do endpoint */ },
  "pagination": {
    "page": 1,
    "per_page": 20,
    "total": 150,
    "total_pages": 8
  },
  "meta": {
    "timestamp": "2026-06-12T10:30:00+00:00",
    "version": "v2"
  }
}
```

### Códigos de erro

| Código | Significado |
|---|---|
| 200 | Sucesso |
| 400 | Bad request (parâmetros inválidos) |
| 401 | Não autenticado |
| 403 | CSRF inválido |
| 404 | Endpoint não encontrado |
| 405 | Método não permitido |
| 422 | Dados inválidos (falha na validação) |
| 429 | Muitas requisições (rate limit) |
| 500 | Erro interno |

---

## 6. Arquitetura do Sistema

### 6.1 Estrutura de diretórios

```
sistema-cameras/
├── api/                         # Endpoints da API (legado + v2)
│   ├── v2/                      #   API REST v2
│   │   ├── api_cameras.php
│   │   ├── api_cadastrar_cameras.php
│   │   ├── api_editar_camera.php
│   │   ├── api_excluir_camera.php
│   │   ├── api_servir_anexo.php
│   │   └── api_upload_anexo.php
│   ├── bootstrap-api.php        # Bootstrap para chamadas API
│   └── ApiResponse.php          # ⚠️ Legado (removido, usar src/Api/)
├── auth/                        # Autenticação
│   ├── login.php                #   Tela de login
│   ├── login_submit.php         #   Processa login
│   ├── logout.php               #   Logout
│   └── recuperar_senha.php      #   Recuperação de senha
├── accounts/                    # Gerenciamento de contas
│   └── gerenciar_usuarios.php
├── config/                      # Configuração
│   ├── config.php               #   Carrega .env, define constantes
│   ├── app.php                  #   Configurações de sessão
│   ├── database.php             #   Classe PDO + helpers
│   └── DB/
│       ├── cftv_gml.sql         #   Schema + dados iniciais
│       └── migrations/          #   Migrations manuais
├── inc/                         # Includes
│   ├── security.php             #   Hash, CSRF, validação senha
│   ├── session_handler.php      #   Handlers de sessão
│   ├── single_session.php       #   Sessão única por usuário
│   ├── navbar.php               #   Barra de navegação
│   ├── header.php               #   Head HTML
│   └── footer.php               #   Footer
├── public/                      # ⚠️ Web root (Apache document root)
│   ├── index.php                #   Front controller
│   ├── .htaccess                #   URL rewriting
│   ├── assets/
│   │   ├── css/
│   │   │   └── main.css
│   │   └── js/
│   │       ├── main.js
│   │       ├── utils/
│   │       │   ├── ui-utils.js      # showToast, escapeHtml
│   │       │   ├── fetchWithTimeout.js
│   │       │   └── file-upload.js
│   │       └── core/
│   │           └── dashboard-core.js
│   ├── uploads/                 #   Uploads de anexos
│   └── sw.js                    #   Service Worker
├── resources/                   # Views (páginas)
│   ├── home.php
│   ├── cadastro_cameras.php
│   ├── editar_cameras.php
│   ├── listar_cameras.php
│   ├── ... (demais páginas)
├── src/                         # Classes PHP (PSR-4)
│   ├── Api/
│   │   ├── ApiResponse.php      #   Respostas padronizadas
│   │   ├── RateLimiter.php      #   Rate limiting (DB)
│   │   └── RequestValidator.php #   Validação de entrada
│   ├── ErrorHandler.php         #   Tratamento global de erros
│   └── Services/
│       └── EquipamentoService.php
├── tests/                       # Testes automatizados
│   ├── Unit/                    #   Testes unitários PHP
│   ├── Api/                     #   Testes de API (HTTP)
│   └── Js/                      #   Testes JavaScript (Vitest)
├── docs/                        # Documentação
├── vendor/                      # Dependências Composer
├── node_modules/                # Dependências Node
├── docker-compose.yml           # Orquestração Docker
├── Dockerfile                   # Imagem PHP-Apache
├── .env                         # ⚠️ Config local (NÃO COMMITAR)
└── setup-xampp.bat              # Configurador XAMPP
```

### 6.2 Fluxo de requisição

```
Browser → Apache → public/.htaccess (rewrite) → public/index.php
                                                      │
                                          ┌───────────┴───────────┐
                                          │                       │
                                    Página web               API REST
                                          │                       │
                                   resources/*.php         api/v2/*.php
                                          │                       │
                                    inc/*.php               src/Api/*.php
                                          │                       │
                                    config/database.php ←───────┘
                                          │
                                       MySQL
```

1. **Apache** recebe a requisição em `public/`
2. `.htaccess` reescreve URLs amigáveis para `index.php`
3. `index.php` carrega config, sessão, segurança
4. A página requisitada (`$page`) é resolvida via `resolvePageScriptPath()`
5. Erros não capturados são tratados por `ErrorHandler` (log + 500 JSON ou página de erro)

### 6.3 Segurança

#### Camadas de proteção

| Camada | Implementação |
|---|---|
| **Senhas** | Bcrypt cost=12, política de senha mista (letras + números + mínimo 6 caracteres) |
| **CSRF** | Token de 64 caracteres por sessão, validado em toda mutation |
| **Rate Limiting** | Janela deslizante no DB: 5 tentativas por IP a cada 15 min (login) |
| **SQL Injection** | Prepared statements com bindValue, whitelist de tabelas no `insert()` |
| **XSS** | CSP nonce-based, `htmlspecialchars()` em saídas, headers `X-Content-Type-Options: nosniff` |
| **Sessão** | HttpOnly, SameSite=Lax, sessão única por usuário, timeout de inatividade |
| **Upload** | Validação MIME type, limite 10MB, autenticação obrigatória para download |
| **CORS** | Validado contra `APP_ALLOWED_ORIGINS`, same-origin por padrão |
| **Headers** | CSP, HSTS (produção), X-Frame-Options: DENY, Referrer-Policy, Permissions-Policy |

#### Política de senha

- Mínimo 6 caracteres (configurável via `PASSWORD_MIN_LENGTH`)
- Deve conter letras e números
- Armazenada com bcrypt (cost=12)
- Senha temporária expira no primeiro login

---

## 7. Desenvolvimento

### 7.1 Requisitos

#### Local (XAMPP)

- PHP 8.1+ com extensões: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `fileinfo`
- Composer 2.x
- Node.js 18+ (para testes JS)
- MySQL 8.0+

#### Docker

- Docker Engine 24+
- Docker Compose v2

#### CI (GitHub Actions)

- PHP 8.3
- MySQL 8.0
- Node.js 20

### 7.2 Testes

#### PHPUnit

```bash
# Todos os testes
composer test

# Com cobertura
composer test-coverage

# Arquivo específico
vendor/bin/phpunit tests/Unit/SecurityTest.php

# Com verbose
vendor/bin/phpunit --verbose

# Filtrar por nome
vendor/bin/phpunit --filter testPasswordPolicyValid
```

#### Vitest (JavaScript)

```bash
# Rodar testes JS
npx vitest run --config tests/Js/vitest.config.ts

# Modo watch
npx vitest --config tests/Js/vitest.config.ts

# Com cobertura
npx vitest run --config tests/Js/vitest.config.ts --coverage
```

#### Testes disponíveis

| Suite | Localização | O que testa |
|---|---|---|
| Unitários | `tests/Unit/` | Funções isoladas (hash, CSRF, validação) |
| API | `tests/Api/` | Endpoints HTTP (requer servidor rodando) |
| JavaScript | `tests/Js/` | Funções frontend (showToast, fetch, CSRF) |

### 7.3 CI/CD

O pipeline do GitHub Actions executa:

1. **PHP Lint** — Verifica sintaxe PHP em todos os arquivos
2. **PHPUnit** — Testes unitários com cobertura
3. **Coverage** — Envia relatório para Codecov
4. **JS Lint** — Valida sintaxe JavaScript
5. **SQL Validate** — Verifica consistência do schema
6. **Smoke Test** — Sobe servidor embutido e testa health check
7. **Security Check** — Busca credenciais hardcoded e verifica `.env` não versionado

### 7.4 Adicionar novo endpoint

#### API

1. Crie o arquivo em `api/v2/api_meu_endpoint.php`:

```php
<?php
declare(strict_types=1);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET permitido', 405);
    }
    // Lógica do endpoint
    $data = ['message' => 'funcionou'];
    ApiResponse::success($data, 'Sucesso');
} catch (Throwable $e) {
    error_log('Erro em api_meu_endpoint: ' . $e->getMessage());
    ApiResponse::internalError();
}
```

2. Registre a rota em `public/index.php` (variável `$page`):

```php
'api/v2/api_meu_endpoint' => __DIR__ . '/../api/v2/api_meu_endpoint.php',
```

3. Adicione testes em `tests/Api/` ou `tests/Unit/`

#### Página web

1. Crie a view em `resources/minha_pagina.php`
2. Adicione JavaScript em `public/assets/js/minha_pagina.js`
3. Registre no `index.php`:

```php
'minha_pagina' => __DIR__ . '/../resources/minha_pagina.php',
```

---

## 8. Troubleshooting

### 8.1 Erro de conexão com banco

```
RuntimeException: DB_HOST não configurado
```

**Causa:** `.env` ausente ou sem DB_HOST.

**Solução:** Copie `.env.example` para `.env` e configure as credenciais.

### 8.2 Tela branca (500)

**Causa:** Erro PHP não capturado.

**Solução:**
1. Verifique os logs: `C:\xampp\php\logs\php_error_log` ou `docker compose logs app`
2. Ative `display_errors` temporariamente em `public/index.php`:
   ```php
   ini_set('display_errors', '1');
   error_reporting(E_ALL);
   ```

### 8.3 Mod_rewrite não funciona (XAMPP)

```
404 ao acessar http://localhost/sistema-cameras/public/
```

**Solução:**
1. Confirme que `LoadModule rewrite_module modules/mod_rewrite.so` está descomentado em `httpd.conf`
2. Confirme `AllowOverride All` para o diretório do projeto
3. Reinicie o Apache

### 8.4 Erro "CSRF token inválido"

**Causa:** Sessão expirada ou token não enviado.

**Solução:**
1. Faça logout e login novamente
2. Verifique se o meta tag `<meta name="csrf-token">` está presente na página
3. Confirme que o header `X-CSRF-Token` está sendo enviado

### 8.5 Upload falha

**Causa:** Arquivo muito grande, tipo não permitido, ou permissão de diretório.

**Solução:**
- Limite: 10MB
- Formatos: JPEG, PNG, GIF, WebP, BMP, PDF, DOC, DOCX, XLS, XLSX
- Verifique permissões de `public/uploads/` (755 ou 775)

### 8.6 Testes PHPUnit falham

```bash
# Limpar cache
rm -rf .phpunit.cache

# Rodar com mais informações
vendor/bin/phpunit --verbose

# Verificar bootstrap
php -f tests/bootstrap.php
```

### 8.7 Porta 8080 já em uso

**Docker:** Altere a porta mapeada em `docker-compose.yml`:
```yaml
ports:
  - "127.0.0.1:8081:80"
```

**PHP embutido:**
```bash
php -S 127.0.0.1:8081 -t public/
```

### 8.8 Erro "No input file specified" (PHP embutido)

Use `-t public/` para apontar para o diretório raiz:
```bash
php -S 127.0.0.1:8080 -t public/
```

---

## 9. Apêndice

### 9.1 Comandos rápidos

#### XAMPP

```batch
:: Iniciar Apache e MySQL
C:\xampp\xampp-control.exe

:: Importar banco
C:\xampp\mysql\bin\mysql -u root -p cftv_gml < config\DB\cftv_gml.sql

:: Exportar banco
C:\xampp\mysql\bin\mysqldump -u root -p cftv_gml > backup.sql

:: Testar PHP
php -v
php -m | findstr pdo
```

#### Docker

```bash
# Iniciar
docker compose up -d

# Parar e remover volumes
docker compose down -v

# Reconstruir
docker compose build --no-cache app

# Executar comando no container
docker compose exec app php vendor/bin/phpunit

# Backup banco
docker compose exec db mysqldump -ucftv_user -p cftv_gml > backup.sql

# Restore banco
docker compose exec -T db mysql -ucftv_user -p cftv_gml < backup.sql
```

#### Git

```bash
# Verificar mudanças
git status
git diff --stat

# Commitar
git add -A
git commit -m "Descrição clara do que foi feito"

# Enviar
git push origin main

# Atualizar local
git pull origin main

# Desfazer mudanças não commitadas
git checkout -- .
```

### 9.2 Variáveis de ambiente detalhadas

| Variável | Obrigatória | Padrão | Descrição |
|---|---|---|---|
| `DB_HOST` | Sim | — | Host do MySQL |
| `DB_NAME` | Sim | — | Nome do banco |
| `DB_USER` | Sim | — | Usuário MySQL |
| `DB_PASS` | Sim | — | Senha MySQL |
| `CAMERAS_ENV` | Não | `development` | Ambiente |
| `APP_TIMEZONE` | Não | `America/Sao_Paulo` | Fuso horário |
| `APP_NAME` | Não | `Sistema de Cameras` | Nome do sistema |
| `CAMERAS_SESSION_TIMEOUT` | Não | `3600` | Timeout inatividade (s) |
| `CAMERAS_SESSION_ABSOLUTE_TIMEOUT` | Não | `28800` | Timeout absoluto (s) |
| `CAMERAS_CSP_ALLOW_INLINE_STYLES` | Não | `0` | 1=permite unsafe-inline |
| `PROXY_TRUSTED_IPS` | Não | vazio | IPs de proxy confiáveis |
| `APP_ALLOWED_ORIGINS` | Não | vazio | Origens CORS |
| `APP_PORT` | Não | `8080` | Porta da aplicação |
| `DB_SSL_CA` | Não | vazio | CA certificado SSL (produção) |

### 9.3 Portas utilizadas

| Serviço | Porta | Local |
|---|---|---|
| Aplicação (Docker) | `127.0.0.1:8080` | Host |
| Aplicação (PHP embutido) | `127.0.0.1:8080` | Host |
| Aplicação (XAMPP) | `80` | Host |
| MySQL (Docker) | `3306` (internal) | Container |
| MySQL (XAMPP) | `3306` | Host |

### 9.4 Referências

- [PHP 8.3 Manual](https://www.php.net/manual/en/)
- [PHPUnit 11 Docs](https://docs.phpunit.de/en/11.0/)
- [Vitest](https://vitest.dev/guide/)
- [Bootstrap 5](https://getbootstrap.com/docs/5.3/getting-started/introduction/)
- [Docker Compose](https://docs.docker.com/compose/)
- [MySQL 8.0](https://dev.mysql.com/doc/refman/8.0/en/)
