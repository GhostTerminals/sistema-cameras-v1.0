<?php
/**
 * Rate Limiter - Controle de taxa de requisições
 * 
 * Estratégias:
 * - Sliding Window (padrão): Janela deslizante baseada em timestamps
 * - Token Bucket: Balde de tokens que se renovam em intervalo fixo
 * 
 * Storage: File-based (JSON) para simplicidade, sem dependências externas
 * 
 * Uso básico:
 *   $limiter = new RateLimiter();
 *   $key = 'cameras:' . $_SERVER['REMOTE_ADDR'];
 *   if (!$limiter->consume($key)) {
 *       ApiResponse::rateLimited($limiter->getRetryAfter($key));
 *   }
 * 
 * Uso com limites customizados:
 *   $limiter->consume($key, 100, 60); // 100 requisições por minuto
 */

class RateLimiter
{
    private const STORAGE_DIR = 'ratelimit';
    private const DEFAULT_CLEANUP_PROBABILITY = 0.01;

    private string $storagePath;
    private string $strategy;
    private array $configs;

    /**
     * @param string|null $storagePath  Diretório para armazenar dados
     * @param string      $strategy     Estratégia: 'sliding_window' ou 'token_bucket'
     * @param array       $configs      Limites por chave (regex => [limit, window])
     */
    public function __construct(
        ?string $storagePath = null,
        string $strategy = 'sliding_window',
        array $configs = []
    ) {
        $this->storagePath = rtrim($storagePath ?? sys_get_temp_dir(), '/') . '/' . self::STORAGE_DIR;
        $this->strategy = $strategy;
        $this->configs = $configs;

        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }

        // Limpeza probabilística para evitar acúmulo
        if (mt_rand(1, 100) / 100 <= self::DEFAULT_CLEANUP_PROBABILITY) {
            $this->cleanup();
        }
    }

    /**
     * Tenta consumir uma requisição para a chave informada
     * 
     * @param string $key    Identificador único (ex: "cameras:192.168.1.100")
     * @param int    $limit  Número máximo de requisições permitidas
     * @param int    $window Janela de tempo em segundos
     * @return bool  True se permitido, false se limite excedido
     */
    public function consume(string $key, int $limit = 60, int $window = 60): bool
    {
        $config = $this->resolveConfig($key, $limit, $window);
        $limit = $config[0];
        $window = $config[1];

        if ($this->strategy === 'token_bucket') {
            return $this->consumeTokenBucket($key, $limit, $window);
        }

        return $this->consumeSlidingWindow($key, $limit, $window);
    }

    /**
     * Obtém quantas requisições ainda podem ser feitas
     */
    public function getRemaining(string $key, int $limit = 60, int $window = 60): int
    {
        $config = $this->resolveConfig($key, $limit, $window);
        $limit = $config[0];

        if ($this->strategy === 'token_bucket') {
            $data = $this->read($key);
            if (!$data) {
                return $limit;
            }
            $tokens = $this->refillTokens($data, $limit, $config[1]);
            return (int)floor($tokens);
        }

        $count = $this->countInWindow($key, $config[1]);
        return max(0, $limit - $count);
    }

    /**
     * Obtém tempo (segundos) até o reset do rate limit
     */
    public function getRetryAfter(string $key, int $limit = 60, int $window = 60): int
    {
        $config = $this->resolveConfig($key, $limit, $window);
        $window = $config[1];

        if ($this->strategy === 'token_bucket') {
            $data = $this->read($key);
            if (!$data) {
                return 0;
            }
            $tokens = $this->refillTokens($data, $config[0], $window);
            if ($tokens >= 1) {
                return 0;
            }
            $refillRate = $config[0] / $window;
            if ($refillRate <= 0) {
                return $window;
            }
            return (int)ceil((1 - $tokens) / $refillRate);
        }

        $data = $this->read($key);
        if (!$data || empty($data['timestamps'])) {
            return 0;
        }

        $oldest = $data['timestamps'][0] ?? 0;
        $elapsed = time() - $oldest;
        return max(0, $window - $elapsed);
    }

    /**
     * Obtém o tempo Unix de quando o rate limit será resetado
     */
    public function getResetTime(string $key, int $limit = 60, int $window = 60): int
    {
        return time() + $this->getRetryAfter($key, $limit, $window);
    }

    /**
     * Obtém cabeçalhos HTTP para rate limit
     */
    public function getHeaders(string $key, int $limit = 60, int $window = 60): array
    {
        $remaining = $this->getRemaining($key, $limit, $window);
        $reset = $this->getResetTime($key, $limit, $window);
        $retryAfter = $this->getRetryAfter($key, $limit, $window);

        return [
            'X-RateLimit-Limit' => (string)$limit,
            'X-RateLimit-Remaining' => (string)$remaining,
            'X-RateLimit-Reset' => (string)$reset,
            'Retry-After' => $retryAfter > 0 ? (string)$retryAfter : '0'
        ];
    }

    /**
     * Envia cabeçalhos de rate limit e verifica se deve bloquear
     * Retorna true se a requisição está dentro do limite
     */
    public function check(string $key, int $limit = 60, int $window = 60): bool
    {
        $allowed = $this->consume($key, $limit, $window);
        $headers = $this->getHeaders($key, $limit, $window);

        // Só enviar headers se não estiver em CLI e headers ainda não foram enviados
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            foreach ($headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        return $allowed;
    }

    /**
     * Estratégia: Sliding Window Log
     * Mantém timestamps das requisições na janela atual
     */
    private function consumeSlidingWindow(string $key, int $limit, int $window): bool
    {
        $data = $this->read($key);
        $now = time();
        $cutoff = $now - $window;

        $timestamps = $data['timestamps'] ?? [];

        // Remover timestamps fora da janela
        $timestamps = array_values(array_filter($timestamps, function ($ts) use ($cutoff) {
            return $ts > $cutoff;
        }));

        if (count($timestamps) >= $limit) {
            // Limite excedido - salvar timestamps filtrados e retornar false
            $data['timestamps'] = $timestamps;
            $this->write($key, $data);
            return false;
        }

        // Adicionar timestamp atual
        $timestamps[] = $now;
        $data['timestamps'] = $timestamps;
        $data['updated_at'] = $now;
        $this->write($key, $data);

        return true;
    }

    /**
     * Estratégia: Token Bucket
     * Tokens são adicionados a uma taxa fixa, requisições consomem tokens
     */
    private function consumeTokenBucket(string $key, int $limit, int $window): bool
    {
        $data = $this->read($key) ?? [];
        $now = time();

        $tokens = $this->refillTokens($data, $limit, $window);

        if ($tokens < 1) {
            $data['tokens'] = $tokens;
            $data['updated_at'] = $now;
            $this->write($key, $data);
            return false;
        }

        $data['tokens'] = $tokens - 1;
        $data['updated_at'] = $now;
        $this->write($key, $data);

        return true;
    }

    /**
     * Reabastece tokens baseado no tempo desde a última atualização
     */
    private function refillTokens(array $data, int $limit, int $window): float
    {
        $now = time();
        $lastRefill = $data['updated_at'] ?? $now;
        $tokens = $data['tokens'] ?? $limit;

        if ($lastRefill >= $now) {
            return $tokens;
        }

        $elapsed = $now - $lastRefill;
        $refillRate = $limit / $window;
        $newTokens = $tokens + ($elapsed * $refillRate);

        return min($limit, $newTokens);
    }

    /**
     * Conta requisições na janela de tempo
     */
    private function countInWindow(string $key, int $window): int
    {
        $data = $this->read($key);
        if (!$data || empty($data['timestamps'])) {
            return 0;
        }

        $cutoff = time() - $window;
        $count = 0;

        foreach ($data['timestamps'] as $ts) {
            if ($ts > $cutoff) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Resolve configuração de limite baseado em regex patterns
     */
    private function resolveConfig(string $key, int $defaultLimit, int $defaultWindow): array
    {
        foreach ($this->configs as $pattern => $config) {
            if (preg_match($pattern, $key)) {
                return [$config[0] ?? $defaultLimit, $config[1] ?? $defaultWindow];
            }
        }
        return [$defaultLimit, $defaultWindow];
    }

    /**
     * Lê dados do storage
     */
    private function read(string $key): ?array
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Escreve dados no storage
     */
    private function write(string $key, array $data): void
    {
        $file = $this->getFilePath($key);
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($content === false) {
            return;
        }

        // Escrita atômica via arquivo temporário
        $tmp = $file . '.tmp.' . getmypid();
        if (file_put_contents($tmp, $content, LOCK_EX) !== false) {
            rename($tmp, $file);
        }
    }

    /**
     * Obtém caminho do arquivo para uma chave
     */
    private function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key); // Mantido SHA-256 para rate limiting (não senhas)
        $prefix = substr($hash, 0, 2);
        $dir = $this->storagePath . '/' . $prefix;

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/' . $hash . '.json';
    }

    /**
     * Remove arquivos expirados (mais de 24h sem atualização)
     */
    public function cleanup(int $maxAge = 86400): int
    {
        $removed = 0;
        $cutoff = time() - $maxAge;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->storagePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                try {
                    $data = json_decode(file_get_contents($file->getPathname()), true);
                    if (is_array($data)) {
                        $updatedAt = $data['updated_at'] ?? 0;
                        if ($updatedAt < $cutoff) {
                            @unlink($file->getPathname());
                            $removed++;
                        }
                    }
                } catch (Exception $e) {
                    // Ignorar erros de leitura
                }
            }
        }

        // Remover diretórios vazios
        $this->removeEmptyDirs($this->storagePath);

        return $removed;
    }

    /**
     * Remove diretórios vazios recursivamente
     */
    private function removeEmptyDirs(string $path): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $dirs = [];
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $dirs[] = $file->getPathname();
            }
        }

        // Ordenar do mais profundo para o mais raso
        rsort($dirs);
        foreach ($dirs as $dir) {
            if (count(scandir($dir)) === 2) { // Apenas . e ..
                @rmdir($dir);
            }
        }
    }

    /**
     * Remove dados de uma chave específica
     */
    public function reset(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    /**
     * Obtém status atual do rate limiter
     */
    public function getStatus(string $key, int $limit = 60, int $window = 60): array
    {
        $remaining = $this->getRemaining($key, $limit, $window);
        $retryAfter = $this->getRetryAfter($key, $limit, $window);
        $resetTime = $this->getResetTime($key, $limit, $window);

        return [
            'limit' => $limit,
            'remaining' => $remaining,
            'retry_after' => $retryAfter,
            'reset_at' => date('Y-m-d\TH:i:s\Z', $resetTime),
            'reset_in_seconds' => $retryAfter,
            'allowed' => $remaining > 0,
            'strategy' => $this->strategy
        ];
    }

    /**
     * Obtém estatísticas do storage
     */
    public function getStats(): array
    {
        $totalFiles = 0;
        $totalSize = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->storagePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'json') {
                $totalFiles++;
                $totalSize += $file->getSize();
            }
        }

        return [
            'storage_path' => $this->storagePath,
            'total_keys' => $totalFiles,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'strategy' => $this->strategy
        ];
    }

    /**
     * Define a estratégia de rate limiting
     */
    public function setStrategy(string $strategy): void
    {
        if (!in_array($strategy, ['sliding_window', 'token_bucket'], true)) {
            throw new InvalidArgumentException("Estratégia inválida: {$strategy}");
        }
        $this->strategy = $strategy;
    }
}
