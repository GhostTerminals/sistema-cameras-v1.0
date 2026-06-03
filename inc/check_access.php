<?php
/**
 * Verifica se o usuario tem permissao para acessar a pagina
 * @param string $nivel_requerido Nivel requerido para a pagina
 * @return bool True se tem acesso, false caso contrario
 */
function verificarAcesso($nivel_requerido = 'user') {
    return userHasAccess($nivel_requerido);
}

/**
 * Redireciona para pagina de nao autorizado se nao tiver acesso
 */
function requererAcesso($nivel_requerido = 'user'): void
{
    if (!verificarAcesso($nivel_requerido)) {
        if (!headers_sent()) {
            header('Location: index.php?page=nao_autorizado');
            exit;
        }
        echo 'Acesso nao autorizado.';
        exit;
    }
}
