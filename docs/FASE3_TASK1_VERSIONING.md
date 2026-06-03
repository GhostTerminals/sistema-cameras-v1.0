# 🚀 API Versioning - Guia de Integração

## Estrutura de Diretórios

```
api/
├── v1/                    # Versão 1 - Compatibilidade
│   ├── api_health.php
│   ├── api_cameras.php
│   ├── api_alarmes.php
│   └── ... (24 endpoints)
│
├── v2/                    # Versão 2 - Melhorada
│   ├── api_health.php     (com ApiResponse)
│   ├── api_cameras.php    (a migrar)
│   └── ... (será expandido)
│
├── ApiRouter.php          # Roteador inteligente
├── ApiResponse.php        # Formato padronizado de respostas
├── bootstrap-api.php      # Ponto de entrada
└── v1/
    └── api_utils.php      # Utilitários legacy
```

---

## Como Usar

### 1. Configurar o Roteador no index.php

Adicione no `public/index.php`:

```php
<?php
// ... código existente ...

// Se requisição for para /api, usar novo router
if (strpos($_SERVER['REQUEST_URI'], '/api') === 0) {
    require_once __DIR__ . '/../api/bootstrap-api.php';
    exit;
}

// ... resto do código ...
?>
```

### 2. Padrão de Resposta v2

Todos os endpoints v2 retornam este formato:

```json
{
  "success": true,
  "code": "SUCCESS",
  "message": "Operação realizada com sucesso",
  "data": { /* dados aqui */ },
  "meta": {
    "timestamp": "2026-05-29T19:57:43Z",
    "version": "v2",
    "request_id": "req_abc123xyz",
    "timezone": "America/Sao_Paulo"
  }
}
```

### 3. Detectar Versão

O router detecta versão automaticamente nesta ordem:

1. **URL path**: `/api/v2/cameras` → v2
2. **Accept header**: `Accept: application/json; version=2` → v2
3. **Custom header**: `X-API-Version: v2` → v2
4. **Default**: v1 (backwards compatible)

### 4. Exemplos de Uso

#### Cliente solicita versão específica via URL

```bash
# Versão 1 (legacy)
curl http://localhost/api/v1/cameras

# Versão 2 (nova)
curl http://localhost/api/v2/cameras
```

#### Cliente solicita via header

```bash
# Via Accept header
curl -H "Accept: application/json; version=2" http://localhost/api/cameras

# Via Custom header
curl -H "X-API-Version: v2" http://localhost/api/cameras
```

#### Info da API

```bash
curl http://localhost/api/

# Resposta:
{
  "success": true,
  "code": "SUCCESS",
  "message": "Operação realizada com sucesso",
  "data": {
    "api_name": "Sistema de Câmeras e Alarmes",
    "current_version": "v2",
    "available_versions": ["v1", "v2"],
    "base_url": "http://localhost/api",
    "documentation": "http://localhost/api/docs",
    "timestamp": "2026-05-29T19:57:43Z"
  }
}
```

---

## Migração de Endpoints para v2

### Passo 1: Copiar arquivo de v1

```bash
cp api/v1/api_cameras.php api/v2/api_cameras.php
```

### Passo 2: Atualizar para usar ApiResponse

**Antes (v1):**

```php
<?php
require_once __DIR__ . '/api_utils.php';
require_once dirname(__DIR__) . '/config/database.php';

apiSendJsonHeaders();
apiRequireMethod('GET');
apiRequireAuth();

try {
    $db = new database();
    $cameras = $db->query("SELECT * FROM tb_cameras");
    
    apiResponse(true, [
        'cameras' => $cameras
    ]);
} catch (Exception $e) {
    apiResponse(false, ['error' => $e->getMessage()], 500);
}
```

**Depois (v2):**

```php
<?php
require_once API_ROOT . '/ApiResponse.php';
require_once APP_ROOT . '/config/database.php';

// Validar método HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('BAD_REQUEST', 'Método HTTP não suportado');
}

// Validar autenticação
configureSessionSecurity();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    ApiResponse::unauthorized();
}

try {
    $db = new database();
    $result = $db->query("SELECT * FROM tb_cameras");
    
    if ($result && $result['status'] === 'success') {
        ApiResponse::success($result['data'] ?? []);
    } else {
        ApiResponse::internalError('Erro ao buscar câmeras');
    }
} catch (Exception $e) {
    ApiResponse::internalError($e->getMessage());
}
```

### Passo 3: Usar novos métodos ApiResponse

```php
// Sucesso com dados
ApiResponse::success($data);

// Lista de itens
ApiResponse::list($items);

// Paginado
ApiResponse::paginated($items, $page, $perPage, $total);

// Recurso criado (201)
ApiResponse::created('camera', $cameraId);

// Não encontrado (404)
ApiResponse::notFound('camera', $id);

// Não autorizado (401)
ApiResponse::unauthorized();

// Acesso negado (403)
ApiResponse::forbidden('Você não tem permissão');

// Erro de validação (422)
ApiResponse::validationError([
    'email' => 'Email inválido',
    'nome' => 'Nome é obrigatório'
]);

// Conflito (409)
ApiResponse::conflict('Câmera com este IP já existe');

// Limite de requisições (429)
ApiResponse::rateLimited(60);

// Erro interno (500)
ApiResponse::internalError('Erro ao processar');
```

---

## Rotas Disponíveis

### Versão 1 (v1) - Legacy

- `GET /api/v1/cameras` - Listar câmeras
- `POST /api/v1/cameras` - Criar câmera
- `PUT /api/v1/cameras/{id}` - Atualizar câmera
- `DELETE /api/v1/cameras/{id}` - Deletar câmera
- ... (todos os 24 endpoints)

### Versão 2 (v2) - Nova

- `GET /api/v2/health` ✅ Implementado
- `GET /api/v2/cameras` (migração em andamento)
- ... (migrações futuras)

---

## Headers de Resposta

Cada resposta inclui:

- `Content-Type: application/json; charset=utf-8`
- `X-API-Version: v2` (versão da API)
- `X-Request-ID: req_abc123xyz` (ID único para rastreamento)
- `X-Content-Type-Options: nosniff`
- `Cache-Control: no-store, no-cache, must-revalidate`

---

## Testes

### Testar roteamento

```bash
# Info da API
curl http://localhost/api/

# Health check v1
curl http://localhost/api/v1/health

# Health check v2
curl http://localhost/api/v2/health

# Com header customizado
curl -H "X-API-Version: v2" http://localhost/api/health
```

### Testar com autenticação

```bash
# Obter token/session primeiro
curl -c cookies.txt http://localhost/auth/login -d "usuario=admin&senha=senha123"

# Usar session
curl -b cookies.txt http://localhost/api/v2/cameras
```

---

## Próximas Etapas

1. ✅ Implementar ApiRouter
2. ✅ Implementar ApiResponse
3. ✅ Estrutura v1/v2 criada
4. 🟡 Migrar endpoints para v2 (requer implementação de Request Validator)
5. 🟡 Integrar com Rate Limiting (Tarefa 3)
6. 🟡 Gerar documentação OpenAPI

---

## Troubleshooting

### Endpoint retorna 404

1. Verificar se arquivo existe em `/api/v1/` ou `/api/v2/`
2. Verificar nome do arquivo (deve ser `api_{recurso}.php`)
3. Verificar se bootstrap-api.php está sendo chamado

### Versão errada sendo usada

1. Verificar URL: `/api/v2/...` usa v2, `/api/v1/...` usa v1
2. Verificar headers: `X-API-Version: v2`
3. Verificar Accept header: `Accept: application/json; version=2`

### Erros de autoload

1. Verificar que `require_once` aponta para arquivo correto
2. Verificar que API_ROOT e APP_ROOT estão definidos em bootstrap-api.php
3. Verificar permissões de arquivo

---

**Status:** ✅ Task 1 - API Versioning Completo  
**Próximo:** Task 2 - Request Validator
