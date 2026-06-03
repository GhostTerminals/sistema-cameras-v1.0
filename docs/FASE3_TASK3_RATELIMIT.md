# 🚦 Task 3: Rate Limiting - Guia de Integração

## Visão Geral

Sistema de rate limiting com duas estratégias:
- **Sliding Window (padrão)**: Janela deslizante baseada em timestamps
- **Token Bucket**: Balde de tokens que se renovam em intervalo fixo

Storage file-based (JSON) em `sys_get_temp_dir()/ratelimit/` — sem dependências externas.

---

## Componentes

### 1. RateLimiter.php

```php
$limiter = new RateLimiter(
    storagePath: '/tmp/ratelimit',
    strategy: 'sliding_window',     // ou 'token_bucket'
    configs: [                      // limites customizados por padrão regex
        '/^auth:/'  => [10, 60],   // 10 req/min
        '/^POST:/'  => [30, 60],
        '/^GET:/'   => [120, 60],
    ]
);
```

#### Métodos Principais

```php
// Consumir 1 requisição (retorna true se permitido)
$limiter->consume($key, $limit, $window);

// Verificar + enviar headers (retorna true se permitido)
$limiter->check($key, $limit, $window);

// Consultar estado
$limiter->getRemaining($key, $limit, $window);   // requisições restantes
$limiter->getRetryAfter($key, $limit, $window);  // segundos até reset
$limiter->getResetTime($key, $limit, $window);   // timestamp do reset
$limiter->getStatus($key, $limit, $window);      // array completo

// Headers de rate limit
$limiter->getHeaders($key, $limit, $window);     // array de headers

// Gerenciamento
$limiter->reset($key);              // resetar chave
$limiter->cleanup($maxAge);         // limpar dados antigos
$limiter->getStats();               // estatísticas do storage
```

### 2. Integração Automática (bootstrap-api.php)

O rate limiting já está integrado no bootstrap-api.php:

```php
// Aplicado automaticamente para todas as requisições /api/*
$rateLimitKey = "{$method}:{$endpoint}:{$clientIp}";
$rateLimiter->check($rateLimitKey);
```

Limites por padrão:
| Padrão | Limite | Janela |
|--------|--------|--------|
| `auth:*` | 10 | 60s |
| `login:*` | 5 | 60s |
| `POST:*` | 30 | 60s |
| `PUT:*` | 30 | 60s |
| `DELETE:*` | 20 | 60s |
| `GET:*` | 120 | 60s |

---

## Headers de Resposta

Toda resposta inclui:

```
X-RateLimit-Limit: 120
X-RateLimit-Remaining: 115
X-RateLimit-Reset: 1717012345
Retry-After: 0
```

Quando o limite é excedido (HTTP 429):

```json
{
  "success": false,
  "code": "RATE_LIMITED",
  "message": "Limite de requisições excedido. Tente novamente mais tarde.",
  "data": {
    "retry_after": 45
  },
  "meta": {
    "timestamp": "2026-05-30T12:00:00Z",
    "version": "v2",
    "request_id": "req_abc123"
  }
}
```

---

## Estratégias

### Sliding Window (padrão)

Mantém timestamps das requisições na janela atual. Preciso e sem picos.

```
Request 1: t=0s  ✓
Request 2: t=10s ✓
Request 3: t=55s ✓ ← ainda na janela de 60s
Request 4: t=61s ✓ ← janela deslizou, request 1 expirou
```

### Token Bucket

Tokens são adicionados a taxa fixa. Permite picos controlados.

```
Capacidade: 10 tokens
Refill: 10 tokens por 60s (1 token a cada 6s)

Pico: 10 requisições imediatas ✓
11ª requisição: ❌ (esperar ~6s por 1 token)
```

---

## Uso em Endpoints Específicos

### Exemplo: Limite customizado em endpoint

```php
<?php
require_once API_ROOT . '/ApiResponse.php';
require_once API_ROOT . '/RateLimiter.php';

$limiter = new RateLimiter();
$key = 'export:relatorios:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

// Limite mais restrito para exportações: 5 req/min
if (!$limiter->consume($key, 5, 60)) {
    ApiResponse::rateLimited($limiter->getRetryAfter($key, 5, 60));
}

// ... processar exportação ...
```

### Exemplo: Ignorar rate limit para admin

```php
configureSessionSecurity();
if (session_status() === PHP_SESSION_NONE) session_start();

$isAdmin = isset($_SESSION['usuario']) && userHasAccess('admin');
if (!$isAdmin) {
    $key = 'cameras:' . $_SERVER['REMOTE_ADDR'];
    $limiter->check($key, 30, 60);
}
```

---

## Testes

```bash
php tests/test-ratelimit.php
```

Resultado esperado:
```
✅ 18/18 testes PASSANDO (100%)
```

---

## Manutenção

### Limpeza automática

O RateLimiter faz limpeza probabilística (1% das requisições) de dados com mais de 24h.

### Limpeza manual

```bash
# Via script PHP
php -r "require 'api/RateLimiter.php'; \$l = new RateLimiter(); echo \$l->cleanup() . ' arquivos removidos';"
```

### Storage

Arquivos em `sys_get_temp_dir()/ratelimit/xx/hash.json`:
- `xx`: primeiros 2 chars do SHA-256 da chave
- Nome: SHA-256 da chave
- Conteúdo: JSON com timestamps/tokens

---

## Próximas Etapas

- Task 4: Error Responses (✅ Completo)
- Task 5: Logging Centralizado
- Task 6: OpenAPI Documentation

---

**Status:** ✅ Task 3 - Rate Limiting Completo
