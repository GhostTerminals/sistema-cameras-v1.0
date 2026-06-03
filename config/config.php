<?php

function loadLocalEnvFile(string $envPath): void
{
    if (!is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        if (
            (getenv($key) !== false && getenv($key) !== '') ||
            (isset($_ENV[$key]) && $_ENV[$key] !== '') ||
            (isset($_SERVER[$key]) && $_SERVER[$key] !== '')
        ) {
            continue;
        }

        $value = trim($value, "\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

// Tenta carregar .env de local seguro externo primeiro (produção),
// depois fallback para o .env na raiz do projeto (desenvolvimento).
$externalEnvPaths = [
    '/etc/sistema-cameras/.env',
    'C:\env\sistema-cameras\.env',
];
$envLoaded = false;
foreach ($externalEnvPaths as $externalPath) {
    if (is_file($externalPath) && is_readable($externalPath)) {
        loadLocalEnvFile($externalPath);
        $envLoaded = true;
        break;
    }
}
if (!$envLoaded) {
    loadLocalEnvFile(__DIR__ . '/../.env');
}

// Padrao global de codificacao/locale para ambiente Linux e Windows.
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}
setlocale(LC_ALL, 'pt_BR.UTF-8', 'pt_BR.utf8', 'pt_BR', 'Portuguese_Brazil.1252');
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');

if (!defined('APP_LOCALE')) {
    define('APP_LOCALE', 'pt-BR');
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', getenv('CAMERAS_ENV') ?: 'development');
}

// Configuracao do banco via variaveis de ambiente.
$envDbHost = getenv('DB_HOST');
$envDbName = getenv('DB_NAME');
$envDbUser = getenv('DB_USER');
$envDbPass = getenv('DB_PASS');

$isProduction = defined('ENVIRONMENT') && ENVIRONMENT === 'production';
if ($isProduction) {
    $missing = [];
    if ($envDbHost === false || $envDbHost === '') {
        $missing[] = 'DB_HOST';
    }
    if ($envDbName === false || $envDbName === '') {
        $missing[] = 'DB_NAME';
    }
    if ($envDbUser === false || $envDbUser === '') {
        $missing[] = 'DB_USER';
    }
    if ($envDbPass === false || $envDbPass === '') {
        $missing[] = 'DB_PASS';
    }
    if (!empty($missing)) {
        $msg = 'Configuracao do banco ausente: ' . implode(', ', $missing);
        error_log($msg);
        throw new RuntimeException($msg);
    }
}

if ($envDbPass === false || $envDbPass === '') {
    $msg = 'Configuracao do banco ausente: DB_PASS';
    error_log($msg);
    throw new RuntimeException($msg);
}

if ($envDbHost === false || $envDbHost === '') {
    throw new RuntimeException('DB_HOST não configurado. Verifique .env ou variáveis de ambiente.');
}
if ($envDbName === false || $envDbName === '') {
    throw new RuntimeException('DB_NAME não configurado. Verifique .env ou variáveis de ambiente.');
}
if ($envDbUser === false || $envDbUser === '') {
    throw new RuntimeException('DB_USER não configurado. Verifique .env ou variáveis de ambiente.');
}
define('DB_HOST', $envDbHost);
define('DB_NAME', $envDbName);
define('DB_USER', $envDbUser);
define('DB_PASS', $envDbPass);
