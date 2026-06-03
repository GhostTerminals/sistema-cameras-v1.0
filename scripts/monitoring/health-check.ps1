param(
    [string]$BaseUrl = "http://localhost:8080",
    [int]$TimeoutSeconds = 10
)

$ErrorActionPreference = "Stop"
$failed = $false

function Check-Url {
    param($Name, $Url, $ExpectedStatus = 401)
    try {
        $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec $TimeoutSeconds
        $actual = [int]$response.StatusCode
        if ($actual -eq $ExpectedStatus) {
            Write-Host "[PASS] $Name → $actual (expected $ExpectedStatus)" -ForegroundColor Green
            return $true
        }
        Write-Host "[FAIL] $Name → $actual (expected $ExpectedStatus)" -ForegroundColor Red
        $script:failed = $true
        return $false
    } catch {
        Write-Host "[FAIL] $Name → $($_.Exception.Message)" -ForegroundColor Red
        $script:failed = $true
        return $false
    }
}

Write-Host "=== Sistema Cameras Health Check ===" -ForegroundColor Cyan
Write-Host "Base URL: $BaseUrl`n" -ForegroundColor Cyan

Check-Url "Auth required" "$BaseUrl/index.php?page=api/api_health"
Check-Url "Login page" "$BaseUrl/index.php?page=login" 200
Check-Url "404 page" "$BaseUrl/index.php?page=nonexistent" 404

if ($failed) {
    Write-Host "`n[FAILED] Some checks did not pass!" -ForegroundColor Red
    exit 1
}
Write-Host "`n[PASS] All checks passed!" -ForegroundColor Green
