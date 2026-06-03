param(
    [string]$BaseUrl = 'http://localhost/sistema-cameras-v1.0/public/index.php',
    [string]$DbName = 'cftv_gml',
    [string]$DbUser = 'root',
    [string]$MysqlPath = 'C:\xampp\mysql\bin\mysql.exe',
    [string]$PhpPath = 'C:\xampp\php\php.exe'
)

$ErrorActionPreference = 'Stop'

function ExtractCsrf([string]$Html) {
    $m = [regex]::Match($Html, 'name="csrf_token"\s+value="([^"]+)"')
    if ($m.Success) { return $m.Groups[1].Value }

    $m2 = [regex]::Match($Html, '<meta\s+name="csrf-token"\s+content="([^"]+)"')
    if ($m2.Success) { return $m2.Groups[1].Value }

    return ''
}

if (!(Test-Path $MysqlPath)) {
    throw "mysql.exe nao encontrado em $MysqlPath"
}
if (!(Test-Path $PhpPath)) {
    throw "php.exe nao encontrado em $PhpPath"
}

$testUser = 'smoke_manut_user'
$testPass = 'Tmp@12345!'
$testName = 'Smoke Manut User'
$token = '[SMOKE_MANUT_' + (Get-Date -Format 'yyyyMMdd_HHmmss') + ']'

$userId = ''
$equipamentoId = ''
$rows = @()

try {
    $u = $testUser.Replace("'", "''")
    $n = $testName.Replace("'", "''")
    $hash = (& $PhpPath -r "echo password_hash('$testPass', PASSWORD_BCRYPT), PHP_EOL;").Trim().Replace("'", "''")

    & $MysqlPath -u $DbUser $DbName -e "
    INSERT INTO usuarios (nome, usuario, senha, nivel_acesso_id, senha_temporaria, ativo)
    VALUES ('$n', '$u', '$hash', 2, 0, 1)
    ON DUPLICATE KEY UPDATE nome=VALUES(nome), senha=VALUES(senha), nivel_acesso_id=2, senha_temporaria=0, ativo=1;
    DELETE FROM login_attempts WHERE username = '$u';
    " | Out-Null

    $userId = (& $MysqlPath -u $DbUser $DbName -N -e "SELECT id FROM usuarios WHERE usuario='$u' LIMIT 1;").Trim()
    $equipamentoId = (& $MysqlPath -u $DbUser $DbName -N -e "SELECT id FROM equipamentos ORDER BY id DESC LIMIT 1;").Trim()

    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

    $loginPage = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + '?page=login') -Method GET -WebSession $session -TimeoutSec 20
    $csrfLogin = ExtractCsrf $loginPage.Content

    Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + '?page=login_submit') -Method POST -WebSession $session -TimeoutSec 20 -Body @{
        csrf_token = $csrfLogin
        text_usuario = $testUser
        text_senha = $testPass
    } | Out-Null

    $homePage = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + '?page=home') -Method GET -WebSession $session -TimeoutSec 20
    $csrfApi = ExtractCsrf $homePage.Content

    $pageCam = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + '?page=manutencao_cameras') -Method GET -WebSession $session -TimeoutSec 20
    $pageAlm = Invoke-WebRequest -UseBasicParsing -Uri ($BaseUrl + '?page=manutencao_alarmes') -Method GET -WebSession $session -TimeoutSec 20

    $pageCamOk = ($pageCam.Content -match 'formManutencaoCamera') -and ($pageCam.Content -match 'assets/js/manutencao_cameras.js')
    $pageAlmOk = ($pageAlm.Content -match 'formManutencaoAlarme') -and ($pageAlm.Content -match 'assets/js/manutencao_alarmes.js')

    $apiCam = Invoke-RestMethod -Uri ($BaseUrl + '?page=api/api_manutencao_cameras&page_num=1&per_page=10&include_lists=1') -Method GET -WebSession $session -TimeoutSec 20
    $apiAlm = Invoke-RestMethod -Uri ($BaseUrl + '?page=api/api_manutencao_alarmes&page_num=1&per_page=10&include_lists=1') -Method GET -WebSession $session -TimeoutSec 20

    $postCamOk = $false
    $postCamDetail = 'N/A (sem equipamento no banco)'
    if (-not [string]::IsNullOrWhiteSpace($equipamentoId)) {
        $payload = @{
            equipamento_id = [int]$equipamentoId
            descricao = "$token teste automatizado"
            data_hora = (Get-Date -Format 'yyyy-MM-ddTHH:mm')
            procedimento_id = ''
            status_id = ''
        } | ConvertTo-Json -Compress

        $respCamPost = Invoke-RestMethod -Uri ($BaseUrl + '?page=api/api_manutencao_cameras') -Method POST -WebSession $session -TimeoutSec 20 -Headers @{ 'X-CSRF-Token' = $csrfApi } -ContentType 'application/json' -Body $payload
        $postCamOk = ($respCamPost.success -eq $true)
        $postCamDetail = if ($postCamOk) { 'success=true' } else { 'success=false' }
    }

    $rows += [PSCustomObject]@{ teste = 'Pagina manutencao cameras'; resultado = $(if($pageCamOk){'PASS'}else{'FAIL'}); detalhe = 'form + js carregados' }
    $rows += [PSCustomObject]@{ teste = 'Pagina manutencao alarmes'; resultado = $(if($pageAlmOk){'PASS'}else{'FAIL'}); detalhe = 'form + js carregados' }
    $rows += [PSCustomObject]@{ teste = 'API GET manutencao cameras'; resultado = $(if($apiCam.success -eq $true){'PASS'}else{'FAIL'}); detalhe = ('success=' + $apiCam.success) }
    $rows += [PSCustomObject]@{ teste = 'API GET manutencao alarmes'; resultado = $(if($apiAlm.success -eq $true){'PASS'}else{'FAIL'}); detalhe = ('success=' + $apiAlm.success) }
    $rows += [PSCustomObject]@{ teste = 'API POST manutencao cameras'; resultado = $(if($postCamOk){'PASS'}else{'FAIL'}); detalhe = $postCamDetail }
    $rows += [PSCustomObject]@{ teste = 'API POST manutencao alarmes'; resultado = 'SKIP'; detalhe = 'Nao executado para evitar alterar observacao de alarmes' }
}
finally {
    $tokenSql = $token.Replace("'", "''")
    if (-not [string]::IsNullOrWhiteSpace($userId)) {
        $u = $testUser.Replace("'", "''")
        & $MysqlPath -u $DbUser $DbName -e "
        DELETE FROM equipamentos_manutencoes WHERE created_by = $userId AND descricao LIKE '$tokenSql%';
        DELETE FROM login_attempts WHERE username = '$u';
        DELETE FROM usuarios WHERE usuario = '$u';
        " | Out-Null
    }
}

$rows | Format-Table -AutoSize
