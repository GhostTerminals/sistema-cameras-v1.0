<?php
/**
 * Validation Schema - Define regras de validação por endpoint
 * 
 * Centraliza todas as validações da API em um único lugar para fácil manutenção.
 * 
 * Uso:
 * $schema = ValidationSchema::get('POST', '/api/cameras');
 * $validator = new RequestValidator($_POST);
 * if (!$validator->validate($schema['rules'], $schema['messages'])) {
 *   ApiResponse::validationError($validator->errors());
 * }
 */

class ValidationSchema
{
    /**
     * Schema de validação por método e endpoint
     */
    private static array $schemas = [];

    /**
     * Obtém schema de validação para endpoint
     */
    public static function get(string $method, string $endpoint): ?array
    {
        $key = "{$method}:{$endpoint}";

        if (!isset(self::$schemas[$key])) {
            // Se não encontrar schema exato, procurar padrão
            foreach (self::$schemas as $pattern => $schema) {
                if (self::patternMatches($pattern, $key)) {
                    return $schema;
                }
            }
            return null;
        }

        return self::$schemas[$key];
    }

    /**
     * Registra schema customizado
     */
    public static function register(string $method, string $endpoint, array $rules, array $messages = []): void
    {
        $key = "{$method}:{$endpoint}";
        self::$schemas[$key] = [
            'rules' => $rules,
            'messages' => $messages
        ];
    }

    /**
     * Verifica se padrão corresponde a chave
     */
    private static function patternMatches(string $pattern, string $key): bool
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        return preg_match("#^{$pattern}$#", $key) === 1;
    }

    /**
     * Inicializar schemas padrão
     */
    public static function initialize(): void
    {
        // ============================================
        // CAMERAS ENDPOINTS
        // ============================================

        // POST /api/cameras - Criar câmera
        self::register('POST', 'cameras', [
            'nome' => 'required|string|max:100',
            'modelo_id' => 'required|numeric',
            'local_id' => 'required|numeric',
            'ip' => 'required|ip',
            'porta' => 'numeric|min:1|max:65535',
            'usuario' => 'string|max:50',
            'senha' => 'string|max:50',
            'numero_serie' => 'string|max:50',
            'status' => 'in:ativo,inativo',
            'anotacoes' => 'string|max:1000'
        ], [
            'nome' => [
                'required' => 'Nome da câmera é obrigatório',
                'max' => 'Nome não pode ter mais de 100 caracteres'
            ],
            'ip' => [
                'required' => 'IP é obrigatório',
                'ip' => 'IP deve ser válido (ex: 192.168.1.1)'
            ]
        ]);

        // PUT /api/cameras/:id - Atualizar câmera
        self::register('PUT', 'cameras/*', [
            'nome' => 'string|max:100',
            'modelo_id' => 'numeric',
            'local_id' => 'numeric',
            'ip' => 'ip',
            'porta' => 'numeric|min:1|max:65535',
            'usuario' => 'string|max:50',
            'senha' => 'string|max:50',
            'numero_serie' => 'string|max:50',
            'status' => 'in:ativo,inativo',
            'anotacoes' => 'string|max:1000'
        ]);

        // ============================================
        // ALARMES ENDPOINTS
        // ============================================

        // POST /api/alarmes - Criar alarme
        self::register('POST', 'alarmes', [
            'camera_id' => 'required|numeric',
            'tipo' => 'required|in:movimento,som,falha,outro',
            'severidade' => 'required|in:baixa,media,alta,critica',
            'descricao' => 'required|string|max:500',
            'data_inicio' => 'required|date:Y-m-d H:i:s',
            'data_fim' => 'date:Y-m-d H:i:s'
        ], [
            'camera_id' => ['required' => 'Câmera é obrigatória'],
            'tipo' => ['required' => 'Tipo de alarme é obrigatório'],
            'severidade' => ['required' => 'Severidade é obrigatória']
        ]);

        // GET /api/alarmes/busca - Buscar alarmes
        self::register('GET', 'alarmes/busca', [
            'termo' => 'string|max:100',
            'tipo' => 'in:movimento,som,falha,outro',
            'severidade' => 'in:baixa,media,alta,critica',
            'data_inicio' => 'date:Y-m-d',
            'data_fim' => 'date:Y-m-d',
            'page' => 'numeric|min:1',
            'per_page' => 'numeric|min:1|max:100'
        ]);

        // ============================================
        // MANUTENÇÃO ENDPOINTS
        // ============================================

        // POST /api/manutencao/cameras - Criar manutenção
        self::register('POST', 'manutencao/cameras', [
            'camera_id' => 'required|numeric',
            'tipo' => 'required|in:preventiva,corretiva,limpeza',
            'data' => 'required|date:Y-m-d',
            'responsavel' => 'required|string|max:100',
            'descricao' => 'string|max:1000',
            'proxima_manutencao' => 'date:Y-m-d'
        ]);

        // ============================================
        // UPLOAD ENDPOINTS
        // ============================================

        // POST /api/upload/anexo - Upload de arquivo
        self::register('POST', 'upload/anexo', [
            'camera_id' => 'required|numeric',
            'tipo' => 'required|in:foto,documento,video',
            'descricao' => 'string|max:500'
            // 'arquivo' é validado separadamente via $_FILES
        ]);

        // ============================================
        // AUTHENTICATION ENDPOINTS
        // ============================================

        // POST /api/auth/login - Login
        self::register('POST', 'auth/login', [
            'usuario' => 'required|string|max:50',
            'senha' => 'required|string|min:6|max:50'
        ], [
            'usuario' => ['required' => 'Usuário é obrigatório'],
            'senha' => [
                'required' => 'Senha é obrigatória',
                'min' => 'Senha deve ter pelo menos 6 caracteres'
            ]
        ]);

        // POST /api/auth/register - Registrar
        self::register('POST', 'auth/register', [
            'nome' => 'required|string|max:100',
            'usuario' => 'required|string|max:50',
            'email' => 'required|email',
            'senha' => 'required|string|min:8|max:50',
            'confirmar_senha' => 'required|string|min:8|max:50',
            'cpf' => 'cpf',
            'telefone' => 'string|max:20'
        ], [
            'email' => ['email' => 'Email deve ser válido'],
            'cpf' => ['cpf' => 'CPF deve ser válido']
        ]);

        // ============================================
        // RELATÓRIOS ENDPOINTS
        // ============================================

        // POST /api/relatorios/cameras - Gerar relatório
        self::register('POST', 'relatorios/cameras', [
            'data_inicio' => 'required|date:Y-m-d',
            'data_fim' => 'required|date:Y-m-d',
            'formato' => 'required|in:pdf,csv,excel',
            'cameras' => 'array',
            'incluir_manutencao' => 'boolean',
            'incluir_alarmes' => 'boolean'
        ]);

        // ============================================
        // DASHBOARD ENDPOINTS
        // ============================================

        // GET /api/dashboard/resumo - Dashboard resumido
        self::register('GET', 'dashboard/resumo', [
            'periodo' => 'in:hoje,semana,mes,ano',
            'incluir_graficos' => 'boolean'
        ]);
    }

    /**
     * Limpar todos os schemas
     */
    public static function clear(): void
    {
        self::$schemas = [];
    }

    /**
     * Obter todos os schemas registrados
     */
    public static function all(): array
    {
        return self::$schemas;
    }
}

// Inicializar schemas padrão quando a classe é carregada
ValidationSchema::initialize();
