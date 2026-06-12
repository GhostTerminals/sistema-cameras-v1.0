<?php

class RateLimiter
{
    private const STORAGE_DIR = 'ratelimit';

    private string $storagePath;
    private string $strategy;
    private array $configs;
    private ?bool $useDb = null;

    public function __construct(
        ?string $storagePath = null,
        string $strategy = 'sliding_window',
        array $configs = []
    ) {
        $this->storagePath = rtrim($storagePath ?? sys_get_temp_dir(), '/') . '/' . self::STORAGE_DIR;
        $this->strategy = $strategy;
        $this->configs = $configs;

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    public function useDatabase(bool $use = true): self
    {
        $this->useDb = $use;
        return $this;
    }

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

    public function getRemaining(string $key, int $limit = 60, int $window = 60): int
    {
        $config = $this->resolveConfig($key, $limit, $window);
        $limit = $config[0];

        if ($this->strategy === 'token_bucket') {
            $data = $this->read($key);
            if (!$data) return $limit;
            $tokens = $this->refillTokens($data, $limit, $config[1]);
            return (int)floor($tokens);
        }

        $count = $this->countInWindow($key, $config[1]);
        return max(0, $limit - $count);
    }

    public function getRetryAfter(string $key, int $limit = 60, int $window = 60): int
    {
        $config = $this->resolveConfig($key, $limit, $window);
        $window = $config[1];

        if ($this->strategy === 'token_bucket') {
            $data = $this->read($key);
            if (!$data) return 0;
            $tokens = $this->refillTokens($data, $config[0], $window);
            if ($tokens >= 1) return 0;
            $refillRate = $config[0] / $window;
            if ($refillRate <= 0) return $window;
            return (int)ceil((1 - $tokens) / $refillRate);
        }

        $data = $this->read($key);
        if (!$data || empty($data['timestamps'])) return 0;
        $oldest = $data['timestamps'][0] ?? 0;
        $elapsed = time() - $oldest;
        return max(0, $window - $elapsed);
    }

    public function getResetTime(string $key, int $limit = 60, int $window = 60): int
    {
        return time() + $this->getRetryAfter($key, $limit, $window);
    }

    public function getHeaders(string $key, int $limit = 60, int $window = 60): array
    {
        return [
            'X-RateLimit-Limit' => (string)$limit,
            'X-RateLimit-Remaining' => (string)$this->getRemaining($key, $limit, $window),
            'X-RateLimit-Reset' => (string)$this->getResetTime($key, $limit, $window),
            'Retry-After' => (string)$this->getRetryAfter($key, $limit, $window),
        ];
    }

    public function check(string $key, int $limit = 60, int $window = 60): bool
    {
        $allowed = $this->consume($key, $limit, $window);
        $headers = $this->getHeaders($key, $limit, $window);

        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            foreach ($headers as $name => $value) {
                $safeName = str_replace(["\r", "\n"], '', $name);
                $safeValue = str_replace(["\r", "\n"], '', $value);
                header("{$safeName}: {$safeValue}");
            }
        }
        return $allowed;
    }

    private function consumeSlidingWindow(string $key, int $limit, int $window): bool
    {
        $data = $this->read($key);
        $now = time();
        $cutoff = $now - $window;

        $timestamps = $data['timestamps'] ?? [];
        $timestamps = array_values(array_filter($timestamps, fn($ts) => $ts > $cutoff));

        if (count($timestamps) >= $limit) {
            $data['timestamps'] = $timestamps;
            $this->write($key, $data);
            return false;
        }

        $timestamps[] = $now;
        $data['timestamps'] = $timestamps;
        $data['updated_at'] = $now;
        $this->write($key, $data);
        return true;
    }

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

    private function refillTokens(array $data, int $limit, int $window): float
    {
        $now = time();
        $lastRefill = $data['updated_at'] ?? $now;
        $tokens = $data['tokens'] ?? $limit;

        if ($lastRefill >= $now) return $tokens;

        $elapsed = $now - $lastRefill;
        $refillRate = $limit / $window;
        return min($limit, $tokens + ($elapsed * $refillRate));
    }

    private function countInWindow(string $key, int $window): int
    {
        $data = $this->read($key);
        if (!$data || empty($data['timestamps'])) return 0;

        $cutoff = time() - $window;
        return count(array_filter($data['timestamps'], fn($ts) => $ts > $cutoff));
    }

    private function resolveConfig(string $key, int $defaultLimit, int $defaultWindow): array
    {
        foreach ($this->configs as $pattern => $config) {
            if (preg_match($pattern, $key)) {
                return [$config[0] ?? $defaultLimit, $config[1] ?? $defaultWindow];
            }
        }
        return [$defaultLimit, $defaultWindow];
    }

    private function canUseDatabase(): bool
    {
        if ($this->useDb === false) return false;
        if ($this->useDb === true) return true;

        static $cached = [];
        $cacheKey = spl_object_id($this);
        if (isset($cached[$cacheKey])) {
            $this->useDb = $cached[$cacheKey];
            return $this->useDb;
        }

        try {
            $pdo = db()->getConnection();
            if ($pdo === null) {
                $this->useDb = false;
                $cached[$cacheKey] = false;
                return false;
            }
            $stmt = $pdo->query("SELECT 1 FROM rate_limits LIMIT 1");
            $this->useDb = true;
            $cached[$cacheKey] = true;
            return true;
        } catch (Throwable $e) {
            $this->useDb = false;
            $cached[$cacheKey] = false;
            return false;
        }
    }

    private function read(string $key): ?array
    {
        if ($this->canUseDatabase()) {
            return $this->dbRead($key);
        }
        return $this->fileRead($key);
    }

    private function write(string $key, array $data): void
    {
        if ($this->canUseDatabase()) {
            $this->dbWrite($key, $data);
        } else {
            $this->fileWrite($key, $data);
        }
    }

    private function dbRead(string $key): ?array
    {
        try {
            $stmt = db()->getConnection()->prepare(
                "SELECT `key`, tokens, updated_at, timestamps FROM rate_limits WHERE `key` = ?"
            );
            $stmt->execute([hash('sha256', $key)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return null;

            $timestamps = [];
            if (!empty($row['timestamps'])) {
                $decoded = json_decode($row['timestamps'], true);
                if (is_array($decoded)) {
                    $timestamps = $decoded;
                }
            }

            return [
                'tokens' => (float)$row['tokens'],
                'updated_at' => (int)$row['updated_at'],
                'timestamps' => $timestamps,
            ];
        } catch (Throwable $e) {
            error_log('[RateLimiter] DB read error: ' . $e->getMessage());
            $this->useDb = false;
            return null;
        }
    }

    private function dbWrite(string $key, array $data): void
    {
        try {
            $hash = hash('sha256', $key);
            $tokens = $data['tokens'] ?? 0;
            $updatedAt = $data['updated_at'] ?? time();
            $timestamps = $data['timestamps'] ?? [];
            $timestampsJson = !empty($timestamps) ? json_encode($timestamps) : null;

            $pdo = db()->getConnection();

            $pdo->prepare(
                "INSERT INTO rate_limits (`key`, tokens, updated_at, timestamps) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE tokens = VALUES(tokens), updated_at = VALUES(updated_at), timestamps = VALUES(timestamps)"
            )->execute([$hash, $tokens, $updatedAt, $timestampsJson]);
        } catch (Throwable $e) {
            error_log('[RateLimiter] DB write error: ' . $e->getMessage());
            $this->useDb = false;
            $this->fileWrite($key, $data);
        }
    }

    private function fileRead(string $key): ?array
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) return null;

        $fp = @fopen($file, 'r');
        if (!$fp) return null;

        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            return null;
        }

        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false) return null;

        $data = json_decode($content, true);
        return is_array($data) ? $data : null;
    }

    private function fileWrite(string $key, array $data): void
    {
        $file = $this->getFilePath($key);
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($content === false) return;

        $fp = @fopen($file, 'c+');
        if (!$fp) return;

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $content);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key);
        $prefix = substr($hash, 0, 2);
        $dir = $this->storagePath . '/' . $prefix;

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/' . $hash . '.json';
    }

    public function cleanup(int $maxAge = 86400): int
    {
        if ($this->canUseDatabase()) {
            return $this->dbCleanup($maxAge);
        }
        return $this->fileCleanup($maxAge);
    }

    private function dbCleanup(int $maxAge): int
    {
        try {
            $cutoff = time() - $maxAge;
            $stmt = db()->getConnection()->prepare(
                "DELETE FROM rate_limits WHERE updated_at < ?"
            );
            $stmt->execute([$cutoff]);
            return $stmt->rowCount();
        } catch (Throwable $e) {
            error_log('[RateLimiter] DB cleanup error: ' . $e->getMessage());
            return 0;
        }
    }

    private function fileCleanup(int $maxAge): int
    {
        $removed = 0;
        $cutoff = time() - $maxAge;

        if (!is_dir($this->storagePath)) return 0;

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
                            $path = $file->getPathname();
                            if (is_file($path)) { unlink($path); }
                            $removed++;
                        }
                    }
                } catch (Exception $e) {}
            }
        }

        $this->removeEmptyDirs($this->storagePath);
        return $removed;
    }

    private function removeEmptyDirs(string $path): void
    {
        if (!is_dir($path)) return;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        $dirs = [];
        foreach ($iterator as $file) {
            if ($file->isDir()) $dirs[] = $file->getPathname();
        }

        rsort($dirs);
        foreach ($dirs as $dir) {
            if (count(scandir($dir)) === 2) {
                @rmdir($dir);
            }
        }
    }
}
