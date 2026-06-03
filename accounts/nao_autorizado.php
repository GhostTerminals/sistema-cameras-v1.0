<?php
require_once __DIR__ . '/../inc/navbar.php';
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h4 class="mb-0">Acesso Não Autorizado</h4>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                    <h5>Você não tem permissão para acessar esta página.</h5>
                    <p class="text-muted">Contate o administrador do sistema se precisar de acesso.</p>
                    <a href="?page=home" class="btn btn-primary">Voltar para Home</a>
                </div>
            </div>
        </div>
    </div>
</div>