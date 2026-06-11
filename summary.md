# Summary - Projeto Concluído

## Resultado Final
- **sistema-cameras-v1.0**: http://10.10.10.203:80 (app) + MySQL 8.0
- **sistema-visitantes-v1.0**: http://10.10.10.203:8080 (app) + MySQL 8.0 porta 3307
- **phpMyAdmin**: http://10.10.10.203:8081
- Todos acessíveis da LAN via IP `10.10.10.203`

## Senhas (visitantes)
| Usuário | Senha |
|---------|-------|
| admin | `Visit@Admina2f4ed25` |
| supervisor | `Visit@Supervisor8ebd352b` |
| user | `Visit@Userf3e0a4c8` |

## Correções Aplicadas
1. **Docker Engine** instalado no WSL2 Debian (sem Docker Desktop)
2. **Port forwarding** configurado (WSL2 ⇄ LAN) com script `port-forward.ps1`
3. **BOM UTF-8 removido** de 6 PHP files (impedia headers/session)
4. **`ob_start()`** adicionado no topo do `index.php` + handler early para `login_submit`
5. **`ob_end_clean()` → `ob_clean()`** no `trocar_senha.php`
6. **entrypoint.sh** corrigido (não sobrescreve env vars do docker-compose)
7. **Backup diário** configurado (3h) em `/opt/backup.sh`
8. **Senha admin** resetada via `password_hash()` bcrypt (estava sendo sobrescrita para texto plano `123456`)

## Infraestrutura
- WSL2 Debian 13 (Trixie) em `172.18.116.113`
- Docker Compose com 3 serviços: app (PHP 8.2 Apache), db (MySQL 8.0), phpmyadmin
- Volumes: `visitorpass_db_data` + `visitantes_fotos`
- Backup: dump MySQL + fotos → `/opt/backups/`
