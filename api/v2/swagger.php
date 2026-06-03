<?php
/**
 * Swagger UI - Interface interativa para API v2
 * 
 * Acesso: GET /api/v2/swagger
 * Especificação OpenAPI: GET /api/v2/swagger-spec
 */

// Detectar tipo de requisição
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$isSwaggerSpec = strpos($requestUri, '/swagger-spec') !== false;
$isSwaggerJson = strpos($requestUri, '/swagger.json') !== false;

// Servir especificação OpenAPI em JSON
if ($isSwaggerSpec || $isSwaggerJson) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }
    
    // Ler arquivo YAML e converter para JSON
    $yamlPath = __DIR__ . '/../../docs/openapi.yaml';
    
    if (!file_exists($yamlPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'OpenAPI specification not found']);
        exit;
    }
    
    // Para produção, converter YAML para JSON usando symfony/yaml
    // Por agora, servir como texto simples com parse básico
    $yamlContent = file_get_contents($yamlPath);
    
    // Simples conversão YAML para JSON (substitui por yaml parser em produção)
    // Se não tiver symfony/yaml, podemos servir o YAML como está
    header('Content-Type: application/x-yaml');
    echo $yamlContent;
    exit;
}

// Servir interface Swagger UI
?>
<!DOCTYPE html>
<html>
<head>
    <title>API Swagger UI - Sistema de Câmeras</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .topbar {
            background-color: #fafafa;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .topbar-container {
            max-width: 1460px;
            margin: 0 auto;
            padding: 0 15px;
        }
        .topbar h1 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        .topbar p {
            margin: 5px 0 0 0;
            font-size: 13px;
            color: #666;
        }
        #swagger-ui {
            max-width: 1460px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="topbar-container">
            <h1>📹 Sistema de Câmeras - API v2</h1>
            <p>Documentação interativa e testes de endpoints</p>
        </div>
    </div>
    
    <div id="swagger-ui"></div>

    <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@3/swagger-ui-bundle.js"></script>
    <script>
        window.onload = function() {
            SwaggerUIBundle({
                url: "/api/v2/swagger-spec",
                dom_id: '#swagger-ui',
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIBundle.SwaggerUIStandalonePreset
                ],
                layout: "StandaloneLayout",
                defaultModelsExpandDepth: 1,
                defaultModelExpandDepth: 1,
                tryItOutEnabled: true,
                requestInterceptor: (request) => {
                    // Adiciona credenciais para requisições autenticadas
                    request.headers['X-Requested-With'] = 'XMLHttpRequest';
                    return request;
                }
            });
        }
    </script>
</body>
</html>
