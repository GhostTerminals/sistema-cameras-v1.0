# Execute como Administrador para expor os serviços na LAN
# Usage: Right-click > "Run with PowerShell (Admin)"

$wslIp = (wsl -d Debian -- hostname -I).Trim().Split(' ')[0]

Write-Host "WSL2 IP: $wslIp" -ForegroundColor Cyan

# Remove existing rules first (if any)
netsh interface portproxy delete v4tov4 listenport=80   listenaddress=0.0.0.0 2>$null
netsh interface portproxy delete v4tov4 listenport=8080 listenaddress=0.0.0.0 2>$null
netsh interface portproxy delete v4tov4 listenport=8081 listenaddress=0.0.0.0 2>$null

# Add port forwarding
netsh interface portproxy add v4tov4 listenport=80   listenaddress=0.0.0.0 connectport=80   connectaddress=$wslIp
netsh interface portproxy add v4tov4 listenport=8080 listenaddress=0.0.0.0 connectport=8080 connectaddress=$wslIp
netsh interface portproxy add v4tov4 listenport=8081 listenaddress=0.0.0.0 connectport=8081 connectaddress=$wslIp

Write-Host "`nPort forwarding configured:" -ForegroundColor Green
netsh interface portproxy show all

Write-Host "`nFirewall rules..." -ForegroundColor Cyan
# Create firewall rules for incoming connections
New-NetFirewallRule -DisplayName "WSL2-Cameras-80" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -ErrorAction SilentlyContinue 2>$null
New-NetFirewallRule -DisplayName "WSL2-Visitantes-8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -ErrorAction SilentlyContinue 2>$null
New-NetFirewallRule -DisplayName "WSL2-PMA-8081" -Direction Inbound -Protocol TCP -LocalPort 8081 -Action Allow -ErrorAction SilentlyContinue 2>$null

Write-Host "`nDone! Acesse os sistemas pelo IP deste computador na LAN:" -ForegroundColor Yellow
Write-Host "  http://<SEU_IP>:80     (Sistema de Cameras)" -ForegroundColor Green
Write-Host "  http://<SEU_IP>:8080   (Sistema de Visitantes)" -ForegroundColor Green
Write-Host "  http://<SEU_IP>:8081   (phpMyAdmin - Visitantes)" -ForegroundColor Green
