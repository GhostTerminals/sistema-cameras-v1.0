<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/config.php";

class database
{
    private static ?self $instance = null;
    private \PDO $pdo;
    private bool $inTransaction = false;

    final public function __construct()
    {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_PERSISTENT => false,
            ];

            if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
                $sslCa = getenv('DB_SSL_CA');
                if ($sslCa !== false && $sslCa !== '') {
                    $options[\PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
                }
                $options[\PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
            }

            $this->pdo = new \PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $err) {
            error_log('Erro de conexao: ' . $err->getMessage());
            throw new \RuntimeException('Erro de conexao com o banco de dados.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): \PDO
    {
        return $this->pdo;
    }

    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->pdo->prepare($sql);

            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    $paramType = PDO::PARAM_STR;
                    
                    if (is_int($value)) {
                        $paramType = PDO::PARAM_INT;
                    } elseif (is_bool($value)) {
                        $paramType = PDO::PARAM_BOOL;
                    } elseif (is_null($value)) {
                        $paramType = PDO::PARAM_NULL;
                    }
                    
                    // Corrigir bind para parametros nomeados e posicionais
                    if (is_int($key)) {
                        // Parametro posicional (comeca em 1, nao 0)
                        $stmt->bindValue($key + 1, $value, $paramType);
                    } else {
                        // Parametro nomeado
                        $stmt->bindValue($key, $value, $paramType);
                    }
                }
            }
            
            $stmt->execute();
            
            // Verificar se e SELECT
            if (stripos(trim($sql), 'SELECT') === 0) {
                $results = $stmt->fetchAll();
                return [
                    'status' => 'success',
                    'data' => $results,
                    'count' => count($results)
                ];
            } else {
                return [
                    'status' => 'success',
                    'affected_rows' => $stmt->rowCount()
                ];
            }

        } catch (\Throwable $err) {
            error_log('Database error: ' . $err->getMessage());
            return [
                'status' => 'error',
                'error' => 'Erro interno no banco de dados.',
                'error_code' => $err->getCode()
            ];
        }
    }
    
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction()
    {
        $this->inTransaction = true;
        return $this->pdo->beginTransaction();
    }
    
    public function commit()
    {
        $this->inTransaction = false;
        return $this->pdo->commit();
    }
    
    public function rollback()
    {
        $this->inTransaction = false;
        return $this->pdo->rollBack();
    }
    
    // Metodo auxiliar para INSERT simples
    public function insert($table, $data)
    {
        // Whitelist de tabelas permitidas para evitar SQL injection
        $tabelasPermitidas = [
            'usuarios', 'user_sessions', 'login_attempts', 'auditoria_eventos',
            'equipamentos', 'equipamentos_camera', 'equipamentos_lpr',
            'equipamentos_dvr', 'equipamentos_totem', 'equipamentos_manutencoes',
            'equipamentos_status_historico', 'central_alarmes', 'alarmes_manutencoes',
            'secretarias', 'locais', 'catalogo_modelos', 'marcas',
            'configuracoes', 'sequencia_codigo_publico', 'equipamentos_anexos',
        ];
        if (!in_array($table, $tabelasPermitidas, true)) {
            throw new \InvalidArgumentException("Tabela nao permitida: $table");
        }
        $columns = array_keys($data);
        foreach ($columns as $col) {
            if (!is_string($col) || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $col)) {
                throw new \InvalidArgumentException("Nome de coluna invalido: $col");
            }
        }
        $columnsSql = '`' . implode('`, `', $columns) . '`';
        $placeholders = ':' . implode(', :', $columns);
        
        $sql = "INSERT INTO `$table` ($columnsSql) VALUES ($placeholders)";
        return $this->query($sql, $data);
    }
}

/**
 * Helper global para obter instancia unica do banco por requisicao.
 * Uso: $db = db();
 */
function db(): database
{
    return database::getInstance();
}


