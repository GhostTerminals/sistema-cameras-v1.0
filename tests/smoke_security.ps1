param(
    [string]$BaseUrl = "http://localhost/sistema-cameras-v1.0/public"
)

$ErrorActionPreference = "Stop"

function Assert-StatusCode {
    param(
        [int]$Actual,
        [int]$Expected,
        [string]$Label
    )

    if ($Actual -ne $Expected) {
        throw "Falha: $Label (esperado=$Expected, atual=$Actual)"
    }

    Write-Host "OK: $Label ($Actual)"
}

function Get-StatusCode {
    param(
        [string]$Url,
        [string]$Method = "GET"
    )

    try {
        $response = Invoke-WebRequest -Uri $Url -Method $Method -UseBasicParsing
        return [int]$response.StatusCode
    } catch {
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            return [int]$_.Exception.Response.StatusCode.value__
        }
        throw
    }
}

$healthUrl = "$BaseUrl/index.php?page=api/api_health"
$sessionCheckUrl = "$BaseUrl/index.php?page=api/session_check"
$renewSessionUrl = "$BaseUrl/index.php?page=api/renovar_sessao"

Assert-StatusCode -Actual (Get-StatusCode -Url $healthUrl -Method "GET") -Expected 401 -Label "api_health sem autenticacao"
Assert-StatusCode -Actual (Get-StatusCode -Url $sessionCheckUrl -Method "GET") -Expected 401 -Label "session_check sem autenticacao"
Assert-StatusCode -Actual (Get-StatusCode -Url $renewSessionUrl -Method "GET") -Expected 401 -Label "renovar_sessao sem autenticacao"

Write-Host "Smoke de seguranca concluido com sucesso."
