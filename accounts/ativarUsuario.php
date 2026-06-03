<?php
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']->nivel_acesso !== 'admin') {
    header('Location: index.php?page=nao_autorizado');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['erro'] = 'Metodo invalido para ativacao de usuario.';
    header('Location: index.php?page=listarUsuario');
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    $_SESSION['erro'] = 'Falha de validacao CSRF.';
    header('Location: index.php?page=listarUsuario');
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['erro'] = 'ID de usuario invalido.';
    header('Location: index.php?page=listarUsuario');
    exit;
}

$db = db();
$before = null;
$beforeResult = $db->query("SELECT u.id, u.nome, u.usuario, n.nome as nivel_acesso, u.ativo, u.senha_temporaria FROM usuarios u LEFT JOIN niveis_acesso n ON u.nivel_acesso_id = n.id WHERE u.id = :id", [':id' => $id]);
if ($beforeResult['status'] === 'success' && !empty($beforeResult['data'])) {
    $before = (array)$beforeResult['data'][0];
}

$sql = "UPDATE usuarios SET ativo = 1 WHERE id = :id";
$params = [':id' => $id];
$result = $db->query($sql, $params);

if ($result['status'] === 'success') {
    $after = $before;
    if (is_array($after)) {
        $after['ativo'] = 1;
    }
    auditEvent($db, 'usuarios', $id, 'UPDATE', $before, $after, 'web');
    $_SESSION['sucesso'] = 'Usuario ativado com sucesso!';
} else {
    $_SESSION['erro'] = 'Erro ao ativar usuario!';
}
header('Location: index.php?page=listarUsuario');
exit;
