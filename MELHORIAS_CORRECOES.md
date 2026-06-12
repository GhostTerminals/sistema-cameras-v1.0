# Plano de Melhorias e Correções - Sistema de Câmeras v1.0

> Análise completa do projeto realizada em Jun/2026.  
> Total de issues encontrados: **5 Críticos, 11 Altos, 18 Médios, ~30 Baixos**  
> Organizados em 8 fases de execução sequencial.

---

## Índice

- [Fase 1 - Correções Críticas de Segurança](#fase-1---correções-críticas-de-segurança)
- [Fase 2 - Correções Altas de Segurança](#fase-2---correções-altas-de-segurança)
- [Fase 3 - Correções de Bugs e Lógica](#fase-3---correções-de-bugs-e-lógica)
- [Fase 4 - Modernização e Infraestrutura](#fase-4---modernização-e-infraestrutura)
- [Fase 5 - Qualidade de Código e Arquitetura](#fase-5---qualidade-de-código-e-arquitetura)
- [Fase 6 - Frontend - Correções e UX](#fase-6---frontend---correções-e-ux)
- [Fase 7 - Testes e CI/CD](#fase-7---testes-e-cicd)
- [Fase 8 - Melhorias Futuras / Backlog](#fase-8---melhorias-futuras--backlog)

---

## Fase 1 - Correções Críticas de Segurança

**Prioridade:** CRÍTICA  
**Estimativa:** 2-3 dias  
**Justificativa:** Estas issues permitem ataques imediatos — brute-force trivial, roubo de arquivos, corrupção de dados e injeção SQL.

---

### 1.1 - Política de Senha Catastrófica

**Arquivos:** `inc/security.php:14,184,215-226`

**Problema:**  
- `PASSWORD_MIN_LENGTH = 6` está bem abaixo do padrão NIST SP 800-63B (mínimo 8)
- `preg_match('/^\d+$/', $password)` exige que a senha contenha **somente números**, reduzindo o espaço de busca para 10^6 = 1.000.000 de combinações
- `generateTemporaryPassword()` gera senhas temporárias numéricas de 6 dígitos — trivialmente brute-forceável

**Correção:**
```
1. Em inc/security.php:
   - Alterar PASSWORD_MIN_LENGTH de 6 para 8 (ideal 10+)
   - Remover a restrição digits-only da validação
   - Exigir mistura de caracteres: pelo menos 1 letra + 1 número
   - generateTemporaryPassword(): usar random_bytes() com 12+ chars alfanuméricos
   - Aumentar bcrypt cost para 12

2. Atualizar testes em tests/Unit/SecurityTest.php:
   - Testar senhas mistas (letras + números + símbolos)
   - Rejeitar senhas só-numéricas
   - Validar custo bcrypt >= 12
```

**Exemplo de código:**
```php
// inc/security.php - nova validação
define('PASSWORD_MIN_LENGTH', 8);

function validatePasswordPolicy(string $password): array {
    $errors = [];
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Senha deve ter no mínimo ' . PASSWORD_MIN_LENGTH . ' caracteres';
    }
    if (preg_match('/^\d+$/', $password)) {
        $errors[] = 'Senha não pode conter apenas números';
    }
    if (!preg_match('/[a-zA-Z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Senha deve conter letras e números';
    }
    return $errors;
}

function generateTemporaryPassword(int $length = 12): string {
    $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
    $bytes = random_bytes($length);
    $result = '';
    for ($i = 0; $i < $length; $i++) {
        $result .= $chars[ord($bytes[$i]) % strlen($chars)];
    }
    return $result;
}

// bcrypt cost 12
password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
```

---

### 1.2 - Senha Temporária Exibida na Tela

**Arquivo:** `auth/recuperar_senha.php:37-53`

**Problema:**  
- A senha temporária é armazenada em `$_SESSION['sucesso_recuperar']` e exibida na tela
- Não há verificação de identidade — qualquer pessoa que saiba o username pode resetar a senha
- A senha fica visível na tela e persiste na sessão

**Correção:**
```
1. NUNCA exibir senhas na tela — remover $_SESSION['sucesso_recuperar'] com a senha
2. Implementar verificação de identidade antes do reset:
   - Enviar código de verificação por e-mail
   - Ou exigir resposta a pergunta de segurança
3. Enviar senha temporária por canal seguro (e-mail criptografado)
4. Forçar troca da senha temporária no próximo login (já existe flag senha_temporaria)
5. Adicionar RateLimiter obrigatório (com autoloader ativo)
```

**Exemplo de código:**
```php
// auth/recuperar_senha.php - nunca exibir senha
if ($resetOk) {
    // Enviar por e-mail em vez de exibir na tela
    enviarSenhaPorEmail($usuario->email, $novaSenha);
    
    $_SESSION['sucesso_recuperar'] = 'Senha temporária enviada para o e-mail cadastrado. '
        . 'Você será obrigado a alterá-la no próximo login.';
    // NUNCA: $_SESSION['sucesso_recuperar'] = $novaSenha;
}
```

---

### 1.3 - Catch Retorna Sucesso Após Exceção (Corrupção de Dados)

**Arquivo:** `api/v2/api_cadastrar_cameras.php:57-76`

**Problema:**  
No bloco `catch`, se `$equipId` existe E o `$rolledBack` é `false` (rollback falhou), o código retorna uma **resposta de sucesso** — mesmo que o estado dos dados seja desconhecido/inconsistente.

**Correção:**
```
1. Após qualquer exceção, SEMPRE retornar resposta de erro
2. Se rollback falhou, registrar o erro criticamente e retornar erro 500
3. Logar o equipId para recuperação manual posterior
4. Aplicar o mesmo padrão em api_editar_camera.php se tiver lógica similar
```

**Exemplo de código:**
```php
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
            $rolledBack = true;
        } catch (Throwable $re) {
            error_log("CRITICAL: Rollback falhou para equipId={$equipId}: " . $re->getMessage());
            $rolledBack = false;
        }
    }
    
    // SEMPRE retornar erro após exceção
    if (!$rolledBack) {
        error_log("CRITICAL: Estado inconsistente - equipId={$equipId} pode estar órfão");
    }
    
    ApiResponse::internalError('Erro ao cadastrar equipamento. Tente novamente.');
}
```

---

### 1.4 - Servir Anexo Sem Autenticação

**Arquivo:** `api/v2/api_servir_anexo.php:8-11`

**Problema:**  
Qualquer pessoa que saiba o ID do anexo pode baixar qualquer arquivo sem estar autenticada.

**Correção:**
```
1. Adicionar verificação de sessão/auth no topo do arquivo
2. Usar EquipamentoService::requireAccess() ou session check
3. Adicionar logging de acesso a anexos para auditoria
```

**Exemplo de código:**
```php
// api/v2/api_servir_anexo.php - topo do arquivo
require_once __DIR__ . '/../bootstrap-api.php';

// Verificar autenticação
if (!isset($_SESSION['usuario'])) {
    ApiResponse::unauthorized('Autenticação necessária para acessar anexos');
}
```

---

### 1.5 - LIMIT/OFFSET Interpolados no SQL

**Arquivo:** `api/v2/api_cameras.php:186`

**Problema:**  
`LIMIT {$perPage} OFFSET {$offset}` usa interpolação de string mesmo com cast `int`. Se o cast for removido em refactor futuro, resulta em injeção SQL.

**Correção:**
```
1. Usar parâmetros preparados: LIMIT ? OFFSET ?
2. PDO suporta bind de int para LIMIT/OFFSET com PDO::PARAM_INT
```

**Exemplo de código:**
```php
// Antes:
$sql .= " LIMIT {$perPage} OFFSET {$offset}";

// Depois:
$sql .= " LIMIT ? OFFSET ?";
// ... no execute:
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
```

---

## Fase 2 - Correções Altas de Segurança

**Prioridade:** ALTA  
**Estimativa:** 2-3 dias  
**Justificativa:** Estas issues permitem bypass de controles de segurança, roubo de sessão e contornamento de rate limiting.

---

### 2.1 - X-Forwarded-Proto Confiado Sem Whitelist de Proxy

**Arquivo:** `config/app.php:38`

**Problema:**  
`isHttpsRequest()` confia cegamente no header `X-Forwarded-Proto`. Um atacante pode definir `X-Forwarded-Proto: https` para enganar a aplicação e definir cookies Secure sobre HTTP (SSL stripping).

**Correção:**
```
1. Adicionar configuração PROXY_TRUSTED_IPS no .env
2. Em isHttpsRequest(), só confiar em X-Forwarded-Proto se REMOTE_ADDR estiver na whitelist
3. Caso contrário, usar apenas $_SERVER['HTTPS']
```

**Exemplo de código:**
```php
function isHttpsRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    
    // Só confiar no proxy header se o request vem de proxy conhecido
    $trustedProxies = defined('PROXY_TRUSTED_IPS') 
        ? explode(',', PROXY_TRUSTED_IPS) 
        : [];
    
    if (in_array($_SERVER['REMOTE_ADDR'] ?? '', $trustedProxies, true)) {
        return (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) 
            && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    return false;
}
```

---

### 2.2 - Bcrypt Cost Baixo (Padrão 10)

**Arquivo:** `inc/security.php:231`

**Problema:**  
`password_hash()` usa cost padrão (10), que está abaixo da recomendação atual (12+).

**Correção:**
```
1. Especificar cost 12 explicitamente em todos os password_hash()
2. Benchmark no hardware de produção para determinar o custo ideal (< 500ms)
3. Implementar re-hash on login: se cost mudou, gerar novo hash
```

---

### 2.3 - CSRF Aceita Token Antigo (Replay Window)

**Arquivo:** `inc/security.php:38-40`

**Problema:**  
`validateCsrfToken()` aceita o token CSRF anterior (`csrf_token_old`), criando uma janela de 2 tokens válidos que amplia a superfície de ataque para replay.

**Correção:**
```
1. Remover a validação do csrf_token_old
2. OU limitar validade do old token a uma janela de tempo curta (ex: 30s)
3. Garantir que AJAX receba o novo token via header X-CSRF-Token
```

---

### 2.4 - IP Spoofing via X-Forwarded-For

**Arquivo:** `inc/single_session.php:7-19`

**Problema:**  
`getSessionClientIp()` confia em `X-Forwarded-For` e `X-Real-IP` sem validar se o request vem de proxy conhecido. Permite spoofing de IP para:
- Impersonar IP de outro usuário nos logs de auditoria
- Bypass de validação de sessão por IP
- Contaminar tabela `user_sessions` com IPs falsos

**Correção:**
```
1. Adicionar whitelist de proxies (mesma config de 2.1)
2. Se REMOTE_ADDR não é proxy, usar apenas REMOTE_ADDR
3. Se é proxy, parsear o último IP confiável do X-Forwarded-For chain
```

---

### 2.5 - Auto-Execução do Bootstrap API

**Arquivo:** `api/bootstrap-api.php:172-173`

**Problema:**  
Se este arquivo for incluído diretamente (sem passar por `index.php`), a guarda permite bypass do CLI e auto-executa a requisição — pulando verificação de timeout e autenticação do front controller.

**Correção:**
```
1. Remover o bloco de auto-execução completamente
2. Requerer invocação explícita: chamar executeApiRequest() somente em index.php
3. Adicionar define('API_INCLUDED_FROM_INDEX', true) em index.php antes do include
```

---

### 2.6 - Endpoints Públicos Sem CSRF

**Arquivo:** `api/bootstrap-api.php:157-163`

**Problema:**  
Endpoints públicos (`auth/login`, `auth/register`) pulam `requireApiCsrf()` — mas login é vulnerável a login CSRF (ataque onde vítima é logged em conta do atacante sem saber).

**Correção:**
```
1. Adicionar validação CSRF para auth/login
2. auth/register (se existir) também precisa de CSRF
3. Separar "não precisa de auth" de "não precisa de CSRF" — são conceitos distintos
```

---

### 2.7 - CORS Valida Contra HTTP_HOST (Client-Controlled)

**Arquivo:** `api/bootstrap-api.php:126-133`

**Problema:**  
A validação de origem CORS constrói a origem esperada a partir de `$_SERVER['HTTP_HOST']`, que é um header controlado pelo cliente. Um atacante pode definir `Origin` e `Host` com o mesmo valor, bypassando a verificação same-origin.

**Correção:**
```
1. Configurar APP_ALLOWED_ORIGIN no .env (ex: https://sistema.example.com)
2. Na validação CORS, comparar contra a origem configurada, não contra HTTP_HOST
3. Suportar múltiplas origens se necessário
```

**Exemplo de código:**
```php
$allowedOrigins = defined('APP_ALLOWED_ORIGINS') 
    ? explode(',', APP_ALLOWED_ORIGINS) 
    : [];

if (!empty($origin) && in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
}
```

---

### 2.8 - RateLimiter: Race Condition no File Storage

**Arquivo:** `src/Api/RateLimiter.php:22-28`

**Problema:**  
O storage baseado em arquivo não usa locking entre `read()` e `write()`. Dois processos concurrentes podem ambos passar na verificação de rate limit (TOCTOU).

**Correção:**
```
1. Usar flock(LOCK_EX) no arquivo de dados durante todo o ciclo read-check-write
2. Alternativa: usar armazenamento só-DB em produção
3. Cleanup para Windows: implementar file_put_contents com LOCK_EX em vez de rename()
```

**Exemplo de código:**
```php
private function checkAndConsume(): bool {
    $lockFile = $this->storagePath . '/' . $key . '.lock';
    $fp = fopen($lockFile, 'w+');
    if (!$fp || !flock($fp, LOCK_EX)) {
        return true; // fail open
    }
    try {
        $data = $this->read($key);
        $allowed = $this->consume($data);
        if ($allowed) {
            $this->write($key, $data);
        }
        return $allowed;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
```

---

### 2.9 - RateLimiter: Sliding Window Descarta Timestamps no DB

**Arquivo:** `src/Api/RateLimiter.php:254-259`

**Problema:**  
`dbWrite()` para a estratégia `sliding_window` armazena apenas `tokens` e `updated_at`, descartando o array `timestamps`. Isso significa que a estratégia degrada silenciosamente para comportamento de token bucket quando usando DB.

**Correção:**
```
1. Adicionar coluna timestamps TEXT/JSON na tabela rate_limits
2. Serializar/deserializar o array de timestamps em dbWrite()/dbRead()
3. OU desabilitar sliding_window quando usando DB e documentar a limitação
```

---

### 2.10 - RequestValidator: Regras Desconhecidas Silenciosamente Passam

**Arquivo:** `src/Api/RequestValidator.php:119-121`

**Problema:**  
Se uma regra tem typo (ex: `'requried|string'`), a validação retorna `true` sem nenhum aviso — o campo fica completamente sem validação.

**Correção:**
```
1. Fazer regras desconhecidas FALHAREM (retornar false + mensagem de erro)
2. Logar warning com error_log() para regra desconhecida
3. Adicionar método addValidator() para registrar validadores customizados
```

**Exemplo de código:**
```php
// Substituir o return true na linha 121
private function applyRule(string $field, $value, string $rule, array $params): bool {
    $method = 'validate' . ucfirst($rule);
    if (!method_exists($this, $method) && !isset($this->customValidators[$rule])) {
        error_log("RequestValidator: regra desconhecida '{$rule}' para campo '{$field}'");
        $this->errors[$field][] = "Regra de validação inválida: {$rule}";
        return false;
    }
    // ... resto da lógica
}
```

---

### 2.11 - fetchWithTimeout Sobrescreve window.fetch Global

**Arquivo:** `public/assets/js/utils/fetchWithTimeout.js:8-18`

**Problema:**  
O módulo sobrescreve `window.fetch` incondicionalmente e descarta qualquer `AbortSignal` fornecido pelo chamador, quebrando callers que usam seu próprio `AbortController`.

**Correção:**
```
1. NÃO sobrescrever window.fetch — exportar como função nomeada
2. Se manter override: usar AbortSignal.any() para mesclar sinais
3. Preservar o signal original do chamador
```

**Exemplo de código:**
```javascript
// Não sobrescrever window.fetch — exportar função
export async function fetchWithTimeout(url, init = {}, timeout = 15000) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);
    
    // Mesclar sinais se caller forneceu um
    const signals = [controller.signal];
    if (init.signal) signals.push(init.signal);
    
    const mergedInit = {
        ...init,
        signal: AbortSignal.any ? AbortSignal.any(signals) : controller.signal
    };
    
    try {
        const response = await fetch(url, mergedInit);
        clearTimeout(timeoutId);
        return response;
    } catch (err) {
        clearTimeout(timeoutId);
        throw err;
    }
}
```

---

## Fase 3 - Correções de Bugs e Lógica

**Prioridade:** MÉDIA  
**Estimativa:** 2-3 dias  
**Justificativa:** Bugs que causam comportamento incorreto, inconsistências e potenciais problemas de dados.

---

### 3.1 - EquipamentoService: Exit em Service Layer

**Arquivo:** `src/Services/EquipamentoService.php:88-96, 25-34`

**Problema:**  
`validateRequiredIds()`, `requireAccess()` e `parseJsonInput()` chamam `ApiResponse::error()` que faz `exit`. Service classes não devem terminar o request — isso impede testes unitários e viola separação de responsabilidades.

**Correção:**
```
1. Criar exceções de domínio: ValidationException, AuthorizationException
2. Service methods lançam exceções em vez de chamar exit
3. API layer captura exceções e converte em ApiResponse
4. Isso permite testar o service isoladamente
```

**Exemplo de código:**
```php
// src/Exceptions/ValidationException.php
class ValidationException extends RuntimeException {
    private array $errors;
    public function __construct(array $errors, string $message = 'Dados inválidos') {
        $this->errors = $errors;
        parent::__construct($message);
    }
    public function getErrors(): array { return $this->errors; }
}

// EquipamentoService.php
private function validateRequiredIds(array $data): void {
    $missing = [];
    foreach (self::REQUIRED_IDS as $field) {
        if (empty($data[$field]) || (int)$data[$field] < 1) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        throw new ValidationException($missing, 'IDs obrigatórios ausentes');
    }
}

// No API endpoint:
try {
    $svc->validateRequiredIds($data);
} catch (ValidationException $e) {
    ApiResponse::error($e->getMessage(), 400, 'VALIDATION_ERROR', $e->getErrors());
}
```

---

### 3.2 - Coerção max(1, ...) para IDs

**Arquivos:** `src/Services/EquipamentoService.php:36-77`, `api/v2/api_editar_camera.php:31-32`

**Problema:**  
`max(1, (int)($data['status_id'] ?? 0))` coage `0` → `1`. Se um usuário envia `id=0`, vira `1`, atualizando o registro errado ou atribuindo status incorreto.

**Correção:**
```
1. Remover max(1, ...) de todos os extractCommonData e IDs
2. Validar que IDs são >= 1 em validateRequiredIds()
3. Lançar exceção se ID for inválido em vez de coageir
```

---

### 3.3 - Sessão: ultimo_acesso Não Atualizado em API

**Arquivo:** `inc/session_handler.php:101-103`

**Problema:**  
`$_SESSION['ultimo_acesso']` só é atualizado em requests não-API. Usuários ativamente usando a API serão eventualmente deslogados.

**Correção:**
```
1. Atualizar ultimo_acesso para requests de API também
2. Ou usar mecanismo separado de "touch" para sessões de API
3. Considerar token renewal para API vs session extension
```

---

### 3.4 - Sessão: session_started_at Ausente Resetado

**Arquivo:** `inc/session_handler.php:20-22`

**Problema:**  
Se `session_started_at` está ausente, é setado para `$now`, dando timeout fresh. Um atacante poderia manipular isso para estender sessões indefinidamente.

**Correção:**
```
1. Se session_started_at está ausente em sessão autenticada, forçar re-login
2. Setar session_started_at APENAS no momento do login
3. Nunca retroativamente inicializar com timestamp atual
```

---

### 3.5 - Dual Rate Limiting Dessincronizado no Login

**Arquivo:** `auth/login_submit.php:87-123`

**Problema:**  
Existem dois sistemas de rate limiting — sessão e DB — que não sincronizam. Após login bem-sucedido, só o DB counter é limpo. Mudança de navegador/session causa contadores inconsistentes.

**Correção:**
```
1. Usar fonte única de verdade: preferir DB (persiste entre sessões)
2. Remover lógica de rate limit baseada em $_SESSION
3. Usar RateLimiter do src/Api/ consistentemente
```

---

### 3.6 - class_exists Com Autoload Desativado

**Arquivo:** `auth/recuperar_senha.php:15`

**Problema:**  
`class_exists('RateLimiter', false)` — o `false` impede autoload. Se a classe não foi carregada ainda, o rate limiting é silenciosamente pulado.

**Correção:**
```
1. Trocar para class_exists('RateLimiter', true)
2. Ou melhor: sempre include/require o RateLimiter explicitamente
3. Rate limiting é obrigatório para recuperação de senha — nunca pular
```

---

### 3.7 - SELECT * No Login (Hash de Senha na Memória)

**Arquivo:** `auth/login_submit.php:128`

**Problema:**  
`SELECT * FROM usuarios` carrega todas as colunas incluindo o hash da senha. Embora seja removido depois, qualquer path de código que esqueça de limpar vaza credenciais.

**Correção:**
```
1. Selecionar apenas colunas necessárias: id, usuario, nome, senha, ativo, nivel_acesso_id, senha_temporaria
2. Nunca usar SELECT * em queries de autenticação
```

---

### 3.8 - api_excluir_camera.php: ID Lido de $_POST

**Arquivo:** `api/v2/api_excluir_camera.php:20`

**Problema:**  
O endpoint lê ID de `$_POST['id']` enquanto outros endpoints leem do JSON body (`php://input`). Inconsistência causa erros silenciosos quando cliente envia JSON.

**Correção:**
```
1. Padronizar leitura de input em todos os endpoints
2. Usar EquipamentoService::parseJsonInput() consistentemente
3. Suportar ambos os formatos durante transição se necessário
```

---

### 3.9 - api_editar_camera.php: Faltando validateCoordenadas

**Arquivo:** `api/v2/api_editar_camera.php:41`

**Problema:**  
`validateCoordenadas()` não é chamado (presente em cadastrar mas faltando em editar). Coordenadas inválidas podem ser armazenadas.

**Correção:**
```
1. Adicionar $svc->validateCoordenadas($fields['coordenadas']) antes do update
2. Consistentemente validar em ambos cadastrar e editar
```

---

### 3.10 - horário UTC Incorreto em ApiResponse

**Arquivo:** `src/Api/ApiResponse.php:292`

**Problema:**  
`date('Y-m-d\TH:i:s\Z')` produz timestamp com sufixo `Z` (indicando UTC), mas `date()` usa timezone do servidor. O `Z` é incorreto se o servidor não está em UTC.

**Correção:**
```
1. Trocar date() para gmdate() para timestamps UTC
2. Ou usar DateTimeImmutable com timezone UTC
```

---

### 3.11 - ErrorHandler: return em vez de exit no Shutdown

**Arquivo:** `src/ErrorHandler.php:88`

**Problema:**  
O shutdown handler usa `return` em vez de `exit`. Se a função foi acionada por erro fatal, funções subsequentes podem executar e vazar output.

**Correção:**
```
1. Trocar return por exit(1) no shutdown handler
2. Consistentemente usar exit() em todos os handlers de erro fatal
```

---

### 3.12 - Verificação de Acesso Duplicada

**Arquivos:** `resources/home.php:5-8`, `resources/cadastro_cameras.php:5-8`

**Problema:**  
Estes arquivos verificam autenticação manualmente (`if (!isset($_SESSION['usuario']))`) em vez de usar o sistema centralizado `access_rules.php` + `check_access.php`.

**Correção:**
```
1. Remover checks manuais de auth das views
2. Usar requererAcesso('user') ou requererAcesso('supervisor') conforme access_rules.php
3. O front controller já garante que check_access foi executado
```

---

## Fase 4 - Modernização e Infraestrutura

**Prioridade:** MÉDIA  
**Estimativa:** 1-2 dias  
**Justificativa:** PHP 8.1 é EOL sem patches de segurança. Configurações de Docker e CI precisam de atualização.

---

### 4.1 - PHP 8.1 EOL - Atualizar para 8.3

**Arquivos:** `Dockerfile:1`, `.github/workflows/ci.yml:17`

**Problema:**  
PHP 8.1 reachou end-of-life em Dez/2024. Sem patches de segurança.

**Correção:**
```
1. Dockerfile: trocar FROM php:8.1-apache para php:8.3-apache
2. composer.json: trocar "php": ">=8.1" para ">=8.2"
3. CI: trocar php-version: '8.1' para '8.3'
4. Testar compatibilidade: rodar phpstan e testes com 8.3
5. Verificar extensões PHP necessárias estão disponíveis para 8.3
```

---

### 4.2 - .dockerignore e Multi-Stage Build

**Arquivos:** `Dockerfile:32-46`, `.dockerignore`

**Problema:**  
O `.env` é copiado para a imagem Docker e depois removido com `rm`, mas persiste nas camadas do histórico da imagem (extraível com `docker history`). O `.dockerignore` pode estar incompleto.

**Correção:**
```
1. Criar/atualizar .dockerignore com:
   .git
   .env
   .env.*
   node_modules
   vendor
   *.sql
   logs/
   .tmp/
   tests/
   docs/
   *.md
   .github
   
2. Usar multi-stage build se necessário para segredos
3. Remover a linha RUN rm -f /var/www/html/.env (já excluído pelo .dockerignore)
```

---

### 4.3 - docker-compose.yml: Porta, Versão, Recursos

**Arquivo:** `docker-compose.yml`

**Problema:**  
- Porta 80 exposta para todas as interfaces (HTTP sem TLS)
- MySQL 8.0 não tem versão fixa (rolling tag)
- Sem limits de recursos
- Sem read-only filesystem

**Correção:**
```yaml
# docker-compose.yml correções
services:
  app:
    ports:
      - "127.0.0.1:8080:80"    # Só localhost, não 0.0.0.0
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: '1.0'
    # read_only: true           # Descomentar quando uploads forem via tmpfs
    # tmpfs:
    #   - /tmp
    #   - /var/www/html/public/uploads
      
  db:
    image: mysql:8.0.40        # Pin versão específica
    deploy:
      resources:
        limits:
          memory: 1G
          cpus: '1.0'
```

---

### 4.4 - .gitignore: Padrões Faltantes

**Arquivo:** `.gitignore`

**Problema:**  
Faltam padrões para certificados SSL, arquivos de backup, `.env.*` variants e docker-compose override.

**Correção:**
```
# Adicionar ao .gitignore:
*.pem
*.key
*.crt
*.p12
*.pfx
*.bak
*.swp
*.tmp
*~
.env.*
!.env.example
!.env.template
docker-compose.override.yml
```

---

### 4.5 - .env.example: Defaults e Placeholders

**Arquivo:** `.env.example`

**Problema:**  
- `CAMERAS_ENV=production` como default (usuário pode não alterar)
- `DB_PASS=CHANGE_ME_DB_PASS` — não obviamente placeholder
- `MYSQL_ROOT_PASS` não deveria estar no .env do app
- `APP_PORT=80` encoraja deploy sem HTTPS

**Correção:**
```
CAMERAS_ENV=development          # Default seguro
DB_PASS=__DEFINA_UMA_SENHA_FORTE_AQUI__
# Remover MYSQL_ROOT_PASS — usar Docker secrets separado
APP_PORT=8080                    # Usar reverse proxy com TLS
```

---

### 4.6 - php.ini de Produção: Diretivas Faltantes

**Arquivo:** `Dockerfile:24`

**Problema:**  
`php.ini-production` é copiado sem Hardening de segurança.

**Correção:**
```
# Adicionar ao Dockerfile após cópia do php.ini:
RUN echo "expose_php = Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "allow_url_fopen = Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "allow_url_include = Off" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "session.cookie_secure = 1" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/security.ini && \
    echo "session.use_strict_mode = 1" >> /usr/local/etc/php/conf.d/security.ini
```

---

### 4.7 - Remover Duplicatas Legadas em api/

**Arquivos:** `api/ApiResponse.php`, `api/RateLimiter.php`, `api/RequestValidator.php`

**Problema:**  
Cópias legadas dos mesmos arquivos em `src/Api/`. A versão `api/RateLimiter.php` tem drift significativo (sem suporte a DB). Se autoloader resolver para a cópia errada, rate limiting degrada.

**Correção:**
```
1. Remover api/ApiResponse.php (idêntico a src/Api/ApiResponse.php)
2. Remover api/RequestValidator.php (idêntico a src/Api/RequestValidator.php)
3. Remover api/RateLimiter.php (versão antiga sem DB)
4. Garantir que api/bootstrap-api.php usa o autoloader (src/ versions)
5. Rodar testes para confirmar que nada quebra
```

---

## Fase 5 - Qualidade de Código e Arquitetura

**Prioridade:** MÉDIA  
**Estimativa:** 2-3 dias  
**Justificativa:** Melhorias de manutenibilidade, consistência e protecão contra regressões.

---

### 5.1 - config.php: Trim de Aspas e Poluição de $_SERVER

**Arquivo:** `config/config.php:39-42`

**Problema:**  
- `trim($value, "\"'")` remove aspas caractere por caractere, não como par
- `$_SERVER` recebe variáveis de env que poluem o array superglobal

**Correção:**
```
1. Implementar parser de aspas que verifica par correspondente
2. Remover $_SERVER do loop — usar apenas putenv() e $_ENV
3. Remover bloco de validação redundante ($isProduction, linhas 87-106)
4. Default de CAMERAS_ENV para 'production' em vez de 'development'
```

---

### 5.2 - database.php: SSL em Produção e Error Codes

**Arquivos:** `config/database.php:16, 100-106`

**Problema:**  
- Sem SSL para conexão DB em produção
- Códigos de erro PDO (SQLSTATE) vazam informações do DB

**Correção:**
```
1. Adicionar MYSQL_ATTR_SSL_CA quando ENVIRONMENT === 'production'
2. Mapear error codes PDO para códigos genéricos da aplicação antes de retornar
3. Adicionar opção PDO::ATTR_PERSISTENT => false explícita
4. Remover dead code: if ($stmt === false) quando ERRMODE_EXCEPTION está ativo
```

---

### 5.3 - RateLimiter: Cleanup e Header Validation

**Arquivos:** `src/Api/RateLimiter.php:26-28, 108-110`

**Problema:**  
- Cleanup roda com 1% probabilidade a cada request — carga em high traffic
- Headers setados com interpolação de string (frágil para CRLF injection)

**Correção:**
```
1. Mover cleanup para cron job separado
2. Validar header values contra CRLF antes de setar
3. Cache de canUseDatabase() em static variable
```

---

### 5.4 - RequestValidator: Inteiros, UTF-8, XSS em Mensagens

**Arquivos:** `src/Api/RequestValidator.php:155, 278, 473-494`

**Problema:**  
- `ctype_digit()` aceita strings numéricas que overflow int; rejeita negativos
- `strlen()` conta bytes, não caracteres (quebra UTF-8)
- Mensagens de erro incluem field name sem escape (XSS)

**Correção:**
```
1. validateInteger: usar filter_var($value, FILTER_VALIDATE_INT) !== false
2. validateLength/Min/Max: usar mb_strlen() para UTF-8
3. Escapar $field com htmlspecialchars() nas mensagens de erro
4. validateRegex: wrap em try/catch + preg_last_error() para ReDoS
```

---

### 5.5 - navbar.php: Loose Comparison

**Arquivo:** `inc/navbar.php:80`

**Problema:**  
`$_SESSION['usuario']->senha_temporaria == 1` usa loose comparison. Se o valor fosse `"1abc"`, compararia como true.

**Correção:**
```
1. Trocar == por === 1 ou === '1' consistentemente
2. Auditar todos os loose comparisons no projeto
```

---

### 5.6 - single_session.php: Race Condition e Write-on-Every-Read

**Arquivos:** `inc/single_session.php:57-75, 131-148`

**Problema:**  
- Race condition entre SELECT e INSERT/UPDATE (sem transação)
- `last_seen` atualizado em cada request (write amplification)

**Correção:**
```
1. Wrap check + register em transação com SELECT ... FOR UPDATE
2. Mover last_seen update para "touch" periódico (a cada 5 min)
3. Adicionar AND active = 1 na query SELECT
```

---

### 5.7 - session_handler.php: Usar db() em vez de new database()

**Arquivo:** `inc/session_handler.php:4-11`

**Problema:**  
`getRequestDatabase()` cria nova instância de DB, bypassando o singleton e potencialmente criando conexão extra.

**Correção:**
```
1. Usar database::getInstance() ou db() em vez de new database()
2. Remover função getRequestDatabase() se desnecessária
```

---

### 5.8 - Audit Logging: Fallback para Escrita de Arquivo

**Arquivo:** `inc/security.php:191-213`

**Problema:**  
Se o DB está down, eventos de auditoria são perdidos silenciosamente — issue de compliance.

**Correção:**
```
1. Adicionar fallback: escrever em arquivo de log se DB insert falhar
2. Triggerar alerta para admins se audit logging falhar
3. Considerar approach baseado em fila (queue) para produção
4. Mapear operações desconhecidas para erro em vez de default INSERT (id 1)
```

---

## Fase 6 - Frontend - Correções e UX

**Prioridade:** MÉDIA  
**Estimativa:** 2-3 dias  
**Justificativa:** Bugs de UX, inconsistências JS, problemas de acessibilidade e performance.

---

### 6.1 - Unificar showToast (Duplicata)

**Arquivos:** `public/assets/js/main.js:1-36`, `public/assets/js/utils/ui-utils.js:18-43`

**Problema:**  
Dois `showToast` implementados com escaping diferente (`&#039;` vs `&#39;`). `ui-utils.js` wins por load order, mas `cadastro_cameras.js` pode chamar antes.

**Correção:**
```
1. Manter APENAS showToast em ui-utils.js (canonical)
2. Em main.js: remover definição e criar alias que aponta para ui-utils
3. Ou: main.js importa de ui-utils.js como módulo
4. Garantir load order consistente
```

---

### 6.2 - Corrigir fetchWithTimeout Override

**Arquivo:** `public/assets/js/utils/fetchWithTimeout.js:8-18`

**Problema:** (Já descrito em 2.11 — items se sobrepõem)

**Correção:**
```
1. Não sobrescrever window.fetch
2. Exportar como fetchWithTimeout() nomeada
3. Usar AbortSignal.any() para mesclar sinais
4. Atualizar todos os callers para usar a função nomeada
```

---

### 6.3 - editar_cameras.js: Form Reset em Sucesso

**Arquivo:** `public/assets/js/editar_cameras.js:324`

**Problema:**  
Após edição bem-sucedida, `this.form.reset()` limpa todos os campos — o usuário perde os dados atuais e o formulário fica em branco.

**Correção:**
```
1. Remover this.form.reset() do sucesso de edição
2. Manter campos preenchidos após sucesso
3. Mostrar toast de sucesso e/ou navegar de volta para listagem
```

---

### 6.4 - Auto-Refresh: Pausar com visibilitychange

**Arquivos:** `public/assets/js/listar_cameras.js:437`, `inc/footer.php:279-298`

**Problema:**  
`setInterval` de auto-refresh roda a cada 2 min (listar) e 30s (session check) mesmo quando a aba está em background — desperdício de bateria e banda.

**Correção:**
```javascript
// Padrão para implementar em ambos
let intervalId;
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(intervalId);
    } else {
        intervalId = setInterval(refreshCallback, interval);
        refreshCallback(); // Refresh imediato ao voltar
    }
});
```

---

### 6.5 - CSRF Vazio: Abortar Request

**Arquivo:** `public/assets/js/cadastro_cameras.js:655-656`

**Problema:**  
Se o meta tag CSRF está ausente, token fallback para string vazia `""` e o POST prossegue sem CSRF.

**Correção:**
```javascript
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
if (!csrfToken) {
    showToast('Erro: token CSRF ausente. Recarregue a página.', 'error');
    return; // Abortar request
}
```

---

### 6.6 - Validar CSRF Token de Resposta

**Arquivo:** `public/assets/js/utils/fetchWithTimeout.js:22-25`

**Problema:**  
Lê `X-CSRF-Token` do response header e atualiza meta tag. Uma response de proxy malicioso poderia injetar token forjado.

**Correção:**
```
1. Validar formato do token antes de atualizar (deve ser 64 hex chars)
2. Só sincronizar de responses same-origin (verificar response.url)
3. Não sincronizar se response.status >= 400
```

---

### 6.7 - Carregamento Condicional do jQuery

**Arquivo:** `inc/header.php:34`

**Problema:**  
jQuery é carregado em todas as páginas, mas só `relatorios.js` usa.

**Correção:**
```
1. Carregar jQuery condicionalmente apenas nas páginas de relatório
2. Migrar relatorios.js para vanilla JS (longo prazo)
3. Reduz payload de ~87KB (jQuery min) na maioria das páginas
```

---

### 6.8 - console.log Suppression Corrigida

**Arquivo:** `inc/header.php:38-39`

**Problema:**  
`if (!window.DEBUG && window.DEBUG !== true)` — a primeira condição sempre avalia true quando DEBUG é undefined/falsy, suprimindo TODOS os logs mesmo em development.

**Correção:**
```javascript
// Antes: if (!window.DEBUG && window.DEBUG !== true)
// Depois:
if (window.DEBUG !== true) {
    console.log = () => {};
    console.warn = () => {};
    console.error = () => {};
}
```

---

### 6.9 - Acessibilidade: Navbar ARIA

**Arquivo:** `inc/navbar.php:8`

**Problema:**  
Navbar toggler button sem `aria-controls`, `aria-expanded` e `aria-label`.

**Correção:**
```html
<button class="navbar-toggler" type="button" 
    data-bs-toggle="collapse" 
    data-bs-target="#navbarNav" 
    aria-controls="navbarNav" 
    aria-expanded="false" 
    aria-label="Toggle navigation">
```

---

### 6.10 - CSV Export: Quoting

**Arquivo:** `public/assets/js/relatorios.js:287-337`

**Problema:**  
Usa ponto-e-vírgula como delimitador mas nem todas as células são quotadas, quebrando alinhamento se dados contêm ponto-e-vírgula.

**Correção:**
```javascript
// Quotar TODAS as células
const csvLine = fields.map(field => {
    const escaped = String(field).replace(/"/g, '""');
    return `"${escaped}"`;
}).join(';');
```

---

### 6.11 - Scrollbar Firefox Fallback

**Arquivo:** `public/assets/css/main.css:959-976`

**Problema:**  
Custom scrollbar só funciona em WebKit/Blink; sem fallback para Firefox.

**Correção:**
```css
/* WebKit/Blink (existente) */
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-thumb { ... }

/* Firefox fallback (adicionar) */
html {
    scrollbar-width: thin;
    scrollbar-color: var(--primary) var(--surface);
}
```

---

## Fase 7 - Testes e CI/CD

**Prioridade:** MÉDIA  
**Estimativa:** 2-3 dias  
**Justificativa:** Cobertura de testes insuficiente, CI com configurações quebradas, sem testes de frontend.

---

### 7.1 - CI: composer install --no-dev Quebra PHPUnit/PHPStan

**Arquivo:** `.github/workflows/ci.yml:24,46`

**Problema:**  
`composer install --no-dev` remove dependências de desenvolvimento (phpunit, phpstan), causando falha nas etapas de lint e teste.

**Correção:**
```
1. Lint step: usar composer install (com dev) — phpstan precisa
2. Test step: usar composer install (com dev) — phpunit precisa
3. --no-dev só em step de Docker build (produção)
```

---

### 7.2 - phpunit.xml: Sintaxe Deprecada PHPUnit 10

**Arquivo:** `phpunit.xml:33-39`

**Problema:**  
Seção `<coverage>` com `<report>` é sintaxe deprecada no PHPUnit 10+.

**Correção:**
```xml
<!-- Remover <report> de dentro de <coverage> -->
<!-- Usar CLI flags: phpunit --coverage-clover=coverage.xml -->
<coverage>
    <include>
        <directory suffix=".php">src</directory>
        <directory suffix=".php">api</directory>
        <directory suffix=".php">inc</directory>
    </include>
</coverage>
```

---

### 7.3 - Atualizar codecov-action

**Arquivo:** `.github/workflows/ci.yml:89`

**Problema:**  
`codecov/codecov-action@v3` está deprecado; v4 é atual.

**Correção:**
```
Trocar codecov/codecov-action@v3 para @v4
Adicionar token: token: ${{ secrets.CODECOV_TOKEN }}
```

---

### 7.4 - Adicionar Testes JavaScript (Vitest)

**Problema:**  
Zero testes JS. Funções críticas como showToast, fetchWithTimeout, máscaras de IP, validações de formulário não são testadas.

**Correção:**
```
1. Já existe vitest no package.json — criar suite de testes
2. Prioridade de testes:
   - showToast (ui-utils.js)
   - fetchWithTimeout
   - IP mask/input validation
   - CSRF token handling
   - Form submission flow
3. Adicionar step npm test no CI
```

---

### 7.5 - Upload Tests: Referenciar Constantes Reais

**Arquivo:** `tests/Unit/FileUploadValidationTest.php`

**Problema:**  
Testes validam contra constantes hardcoded no próprio teste, não contra as constantes reais da aplicação. Se a allowlist muda, testes ainda passam.

**Correção:**
```
1. Importar/recuperar ALLOWED_MIMES e MAX_SIZE do arquivo de upload real
2. Ou: testar contra o endpoint de upload (integration test)
3. Adicionar teste que falha se allowlist mudar sem atualizar teste
```

---

### 7.6 - API Tests: Adicionar Testes Autenticados

**Arquivo:** `tests/Api/CameraApiTest.php`

**Problema:**  
Testes só verificam 401 (não autenticado) — sem testes de CRUD autenticado, validação de dados ou edge cases.

**Correção:**
```
1. Adicionar helper de sessão autenticada nos testes
2. Testes para:
   - CRUD completo de câmeras (criar, listar, editar, excluir)
   - Validação de campos obrigatórios
   - Paginação e filtros
   - Rate limiting efetivo
   - Response format consistency
3. Mock ou seed de dados para testes
```

---

### 7.7 - SecurityTest: Validar Política Forte

**Arquivo:** `tests/Unit/SecurityTest.php:68-73`

**Problema:**  
Teste de senha valida que `123456` passa — confirma a fraqueza em vez de detectá-la.

**Correção:**
```
1. Após corrigir política (Fase 1), atualizar testes:
   - 123456 deve FALHAR
   - Senhas só-numéricas devem FALHAR
   - Senhas mistas de 8+ chars devem PASSAR
   - Custo bcrypt >= 12
2. Adicionar teste para generateTemporaryPassword() — não numérico
```

---

### 7.8 - bootstrap.php: file_exists Antes de Require

**Arquivo:** `tests/bootstrap.php:5-8`

**Problema:**  
Requiere arquivos de config que podem não existir em CI (sem `.env`), causando fatal errors.

**Correção:**
```php
// Antes:
require_once __DIR__ . '/../config/config.php';

// Depois:
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
}
// Fallback para variáveis de ambiente de CI
```

---

### 7.9 - Adicionar Lint JS/CSS no CI

**Arquivo:** `.github/workflows/ci.yml`

**Problema:**  
Sem validação de código frontend no pipeline CI.

**Correção:**
```
1. Adicionar step de JS lint (ESLint ou php -l equivalente)
2. Adicionar step de CSS validation (stylelint)
3. Adicionar step npm test (Vitest) no CI
4. Step: npm install && npm run lint && npm test
```

---

### 7.10 - Validar SQL Dump no CI

**Arquivo:** `.github/workflows/ci.yml:55-56`

**Problema:**  
O SQL dump pode não existir ou estar desatualizado — sem validação.

**Correção:**
```
Adicionar step antes da importação:
- name: Validate SQL dump exists
  run: test -f config/DB/cftv_gml.sql
```

---

## Fase 8 - Melhorias Futuras / Backlog

**Prioridade:** BAIXA  
**Estimativa:** Contínuo  
**Justificativa:** Itens que melhorariam a qualidade geral mas não são urgentes.

---

### 8.1 - Prevenção de ReDoS no Validator de Regex

**Arquivo:** `src/Api/RequestValidator.php:334-340`

- `validateRegex` passa padrões de regex fornecidos pelo usuário diretamente para `preg_match()`
- Wrap em try-catch + `preg_last_error()` check

---

### 8.2 - Audit Logging: Fallback para Arquivo + Alertas

**Arquivo:** `inc/security.php:191-213`

- Se DB insert falha, escrever em arquivo de log dedicado
- Enviar alerta para admin se audit logging falhar consistentemente
- Considerar fila (RabbitMQ/Redis) para produção

---

### 8.3 - EquipamentoService: UPDATE Dinâmico

**Arquivo:** `src/Services/EquipamentoService.php:290-318`

- Hoje atualiza TODOS os campos, mesmo inalterados
- Construir UPDATE dinâmico com apenas campos modificados
- Reduz writes desnecessários e dispara de audit

---

### 8.4 - Dashboard: fetchWithTimeout + Timeout

**Arquivo:** `public/assets/js/core/dashboard-core.js:9-11`

- `fetchData` sem timeout, sem credentials, error handling genérico
- Usar `fetchWithTimeout` e adicionar error typing

---

### 8.5 - Remover DOMContentLoaded Redundante

**Arquivo:** `public/assets/js/listar_cameras.js:399-400`

- IIFE roda no parse time mas envolve setup em `DOMContentLoaded`
- Se script tem `defer` ou está no final do body, evento já disparou
- Remover listener interno e executar setup diretamente

---

### 8.6 - Uppercase Automático: Excluir Email

**Arquivo:** `public/assets/js/utils/uppercase.js:14-18`

- Upper-casing todo input conflita com email e outros campos
- Adicionar `data-uppercase="false"` em campos de email
- Garantir que HTML passa essa flag

---

### 8.7 - Collapse de Keyframes CSS Duplicados

**Arquivos:** `main.css:942-952`, `home.css:85-97`

- `@keyframes pulse` redefinido com comportamento diferente
- Renomear um para `pulseShadow` ou `pulseGlow`

---

### 8.8 - Declarar Variáveis CSS de Dark Mode em :root

**Arquivos:** `main.css:1056-1192`, `theme-enhancements.css`

- Dark mode referencia variáveis não declaradas em `:root`
- Mover declarações para `:root` com `[data-theme="dark"]`

---

### 8.9 - Migrar relatorios.js de jQuery para Vanilla JS

**Arquivo:** `public/assets/js/relatorios.js`

- Único arquivo que usa jQuery
- Remover dependência de ~87KB para uma página

---

### 8.10 - Aviso de Senha Temporária Não-Dismissible

**Arquivo:** `inc/navbar.php:129-136`

- Alerta de senha temporária tem `btn-close` — usuário pode dispensar e esquecer
- Tornar não-dismissible ou re-exibir a cada page load até trocar

---

### 8.11 - Content-Disposition: Attachment em Servir Anexo

**Arquivo:** `api/v2/api_servir_anexo.php:56`

- Usar `Content-Disposition: attachment` por padrão
- Só usar `inline` para imagens seguras
- Impede execução de JavaScript em PDFs no browser

---

### 8.12 - Read-Only Filesystem no Docker

**Arquivo:** `docker-compose.yml`

- Adicionar `read_only: true` no container app
- Montar tmpfs para `/tmp` e `/var/www/html/public/uploads`
- Reduz superfície de ataque se container for comprometido

---

### 8.13 - Acessibilidade:reload Button no relatorios.js

**Arquivo:** `public/assets/js/relatorios.js:270`

- `onclick="location.reload()"` inline não é keyboard-accessible
- Usar `<button>` com event listener

---

### 8.14 - CSS: Remover Duplicate :root em home.css

**Arquivo:** `public/assets/css/pages/home.css:1-9`

- `:root` block redeclara variáveis já em `main.css`
- Remover duplicata, depender de `main.css`

---

### 8.15 - EquipamentoService: UPPER() Impede Índice

**Arquivo:** `src/Services/EquipamentoService.php:110-113`

- `UPPER(nome) = UPPER(?)` causa full table scan
- Criar coluna gerada `nome_upper` com índice
- Ou usar COLLATE com case-insensitive nativo do MySQL

---

## Resumo de Prioridades

| Fase | Prioridade | Issues | Estimativa |
|------|-----------|--------|------------|
| Fase 1 | CRÍTICA | 5 | 2-3 dias |
| Fase 2 | ALTA | 11 | 2-3 dias |
| Fase 3 | MÉDIA | 12 | 2-3 dias |
| Fase 4 | MÉDIA | 7 | 1-2 dias |
| Fase 5 | MÉDIA | 8 | 2-3 dias |
| Fase 6 | MÉDIA | 11 | 2-3 dias |
| Fase 7 | MÉDIA | 10 | 2-3 dias |
| Fase 8 | BAIXA | 15 | Contínuo |
| **TOTAL** | | **79** | **~18-22 dias** |

---

## Ordem de Execução Recomendada

1. **Fase 1** → Bloqueia ataques imediatos (senha,ファイルs, SQL, corrupção)
2. **Fase 2** → Fecha bypasses de segurança (proxy, CSRF, CORS, rate limit)
3. **Fase 3** → Corrige bugs de lógica que causam dados incorretos
4. **Fase 4** → Atualiza infra (PHP 8.3, Docker hardened)
5. **Fase 5** → Melhora arquitetura e qualidade do código
6. **Fase 6** → Corrige UX e frontend
7. **Fase 7** → Expande cobertura de testes
8. **Fase 8** → Backlog de melhorias contínuas

> Cada fase deve ser seguida de rodar a suite de testes completa e validação manual antes de prosseguir à próxima.
