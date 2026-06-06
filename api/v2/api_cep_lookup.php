<?php

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET é permitido');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    $cep = preg_replace('/\D+/', '', (string)($_GET['cep'] ?? ''));
    if (strlen($cep) !== 8) {
        ApiResponse::error('VALIDATION_ERROR', 'CEP inválido.');
    }

    $url = 'https://viacep.com.br/ws/' . $cep . '/json/';

    $raw = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'sistema-cameras/1.0'
        ]);
        $raw = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode >= 400) {
            ApiResponse::error('SERVICE_UNAVAILABLE', 'Falha ao consultar CEP.');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "Accept: application/json\r\nUser-Agent: sistema-cameras/1.0\r\n"
            ]
        ]);
        $raw = file_get_contents($url, false, $context);
        if ($raw === false) {
            ApiResponse::error('SERVICE_UNAVAILABLE', 'Falha ao consultar CEP.');
        }
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        ApiResponse::error('SERVICE_UNAVAILABLE', 'Resposta inválida do serviço de CEP.');
    }

    if (!empty($data['erro'])) {
        ApiResponse::notFound('CEP');
    }

    ApiResponse::success([
        'cep' => $data['cep'] ?? null,
        'logradouro' => $data['logradouro'] ?? null,
        'complemento' => $data['complemento'] ?? null,
        'bairro' => $data['bairro'] ?? null,
        'cidade' => $data['localidade'] ?? null,
        'uf' => $data['uf'] ?? null,
    ]);

} catch (Throwable $e) {
    error_log('[api_cep_lookup] ' . $e->getMessage());
    ApiResponse::internalError('Falha ao consultar CEP.');
}
