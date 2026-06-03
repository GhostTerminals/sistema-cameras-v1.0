<?php
ob_start();
session_start();
// Include necessary files
require_once __DIR__ . '/../inc/navbar.php';
require_once __DIR__ . '/../config/database.php';

// Verificacao de acesso admin
if (!isset($_SESSION['usuario']) || $_SESSION['usuario']->nivel_acesso !== 'admin') {
    header('Location: index.php?page=nao_autorizado');
    exit;
}
$db = db();

function verifica_usuario($dbConn, $usuario) {
    try {
        $sql = "SELECT COUNT(*) AS total FROM usuarios WHERE usuario = :usuario";
        $params = [':usuario' => $usuario];
        $result = $dbConn->query($sql, $params);

        if ($result['status'] === 'success' && count($result['data']) > 0) {
            $row = $result['data'][0];
            if ($row->total >= 1) {
                $_SESSION['usuario_existe'] = true;
                return true; // Usuario existe
            }
        }
        return false; // Usuario nao existe
    } catch (Exception $ex) {
        error_log('Erro ao verificar usuario: ' . $ex->getMessage());
        return false;
    }
}

function verifica_nome($dbConn, $nome) {
    try {
        $sql = "SELECT COUNT(*) AS total FROM usuarios WHERE nome = :nome";
        $params = [':nome' => $nome];
        $result = $dbConn->query($sql, $params);

        if ($result['status'] === 'success' && count($result['data']) > 0) {
            $row = $result['data'][0];
            if ($row->total >= 1) {
                $_SESSION['nome_existe'] = true;
                return true; // Nome existe
            }
        }
        return false; // Nome nao existe
    } catch (Exception $ex) {
        error_log('Erro ao verificar nome: ' . $ex->getMessage());
        return false;
    }
}

function inserirUsuario($dbConn) {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
            $csrfToken = $_POST['csrf_token'] ?? null;
            if (!validateCsrfToken($csrfToken)) {
                $_SESSION['erro_cadastro'] = 'Falha de validacao CSRF.';
                ob_end_clean();
                header('Location: index.php?page=administracao');
                exit;
            }

            $nome = trim($_POST['text_nome'] ?? '');
            $usuario = trim($_POST['text_usuario'] ?? '');
            $senha = $_POST['text_senha'] ?? '';
            $nivel_acesso = $_POST['text_nivel_acesso'] ?? 'user';
            if (!in_array($nivel_acesso, ['admin', 'supervisor', 'user'], true)) {
                $nivel_acesso = 'user';
            }

            // Converter nome do nível de acesso para ID
            $nivel_acesso_id = 3; // padrão: user
            if ($nivel_acesso === 'admin') {
                $nivel_acesso_id = 1;
            } elseif ($nivel_acesso === 'supervisor') {
                $nivel_acesso_id = 2;
            }

            if (empty($nome) || empty($usuario) || empty($senha)) {
                $_SESSION['erro_cadastro'] = 'Todos os campos sao obrigatorios!';
                ob_end_clean();
                header('Location: index.php?page=administracao');
                exit;
            }

            $passwordErrors = [];
            if (!validatePasswordPolicy($senha, $passwordErrors)) {
                $_SESSION['erro_cadastro'] = implode(' ', $passwordErrors);
                ob_end_clean();
                header('Location: index.php?page=administracao');
                exit;
            }

            if (verifica_usuario($dbConn, $usuario)) {
                ob_end_clean();
                header('Location: index.php?page=administracao');
                exit;
            }

            if (verifica_nome($dbConn, $nome)) {
                ob_end_clean();
                header('Location: index.php?page=administracao');
                exit;
            }

            $senha_hash = hashPassword($senha);

            $sql = "INSERT INTO usuarios (nome, usuario, senha, nivel_acesso_id, senha_temporaria) VALUES (:nome, :usuario, :senha, :nivel_acesso_id, 1)";
            $params = [
                ':nome' => $nome,
                ':usuario' => $usuario,
                ':senha' => $senha_hash,
                ':nivel_acesso_id' => $nivel_acesso_id
            ];

            $result = $dbConn->query($sql, $params);

            if ($result['status'] === 'success') {
                $newId = (int)$dbConn->lastInsertId();
                auditEvent($dbConn, 'usuarios', $newId, 'INSERT', null, [
                    'id' => $newId,
                    'nome' => $nome,
                    'usuario' => $usuario,
                    'nivel_acesso' => $nivel_acesso,
                    'senha_temporaria' => 1
                ], 'web');

                $_SESSION["status_cadastro"] = true;
                ob_end_clean();
                header('Location: index.php?page=administracao');
                exit;
            } else {
                $_SESSION['erro_cadastro'] = 'Erro ao cadastrar usuario!';
                ob_end_clean();
                header('Location: index.php?page=administracao');
                exit;
            }
        }
    } catch (Exception $ex) {
        $_SESSION['erro_cadastro'] = 'Erro ao cadastrar usuario!';
        ob_end_clean();
        header('Location: index.php?page=administracao');
        exit;
    }
}

// Function to list users from the database
function listarUsuarios($dbConn) {
    try {
        $sql = "SELECT u.id, u.nome, u.usuario, n.nome as nivel_acesso, u.created_at AS data, u.senha_temporaria FROM usuarios u LEFT JOIN niveis_acesso n ON u.nivel_acesso_id = n.id ORDER BY u.id DESC";
        $result = $dbConn->query($sql);

        if ($result['status'] === 'success') {
            return $result['data'];
        }
        return [];
    } catch (Exception $e) {
        error_log('Erro ao buscar usuarios: ' . $e->getMessage());
        return [];
    }
}

inserirUsuario($db);
$listUsuarios = listarUsuarios($db);
?>

<div class="container mt-4 mb-5">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-1"><i class="fas fa-users me-2"></i>Usu&aacute;rios Cadastrados</h3>
                <p class="text-muted mb-0">Gerencie os usu&aacute;rios do sistema.</p>
            </div>
            <a href="?page=administracao" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Voltar
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (isset($_SESSION['erro_cadastro'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['erro_cadastro'], ENT_QUOTES, 'UTF-8') ?>
                    <?php unset($_SESSION['erro_cadastro']); ?>
                </div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>Usu&aacute;rio</th>
                            <th>N&iacute;vel de Acesso</th>
                            <th>Data de Cadastro</th>
                            <th>Status Senha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($listUsuarios)): ?>
                            <?php foreach ($listUsuarios as $usuario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($usuario->id ?? '') ?></td>
                                    <td><?= htmlspecialchars($usuario->nome ?? '') ?></td>
                                    <td><?= htmlspecialchars($usuario->usuario ?? '') ?></td>
                                    <td>
                                        <span class="badge 
                                            <?php
                                                switch($usuario->nivel_acesso) {
                                                    case 'admin':
                                                        echo 'bg-danger';
                                                        break;
                                                    case 'supervisor':
                                                        echo 'bg-info';
                                                        break;
                                                    default:
                                                        echo 'bg-primary';
                                                }
                                            ?>">
                                            <?= htmlspecialchars($usuario->nivel_acesso ?? 'user') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($usuario->data ?? '') ?></td>
                                    <td>
                                        <?php if ($usuario->senha_temporaria == 1): ?>
                                            <span class="badge bg-warning">Senha Tempor&aacute;ria</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Senha Definida</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">Nenhum usu&aacute;rio cadastrado</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
