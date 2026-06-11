# Manual de Implantação

Sistemas **Sistema de Câmeras e Alarmes** (porta 80) e **Sistema de Visitantes** (porta 8080).

---

## Sumário

- [1. Visão Geral da Arquitetura](#1-visão-geral-da-arquitetura)
- [2. Cenário A — WSL2 (Desenvolvimento Local)](#2-cenário-a--wsl2-desenvolvimento-local)
  - [2.1. Pré-requisitos](#21-pré-requisitos)
  - [2.2. Instalação do Docker Engine no WSL2](#22-instalação-do-docker-engine-no-wsl2)
  - [2.3. Clonar Repositórios](#23-clonar-repositórios)
  - [2.4. Configurar .env](#24-configurar-env)
  - [2.5. Build e Deploy](#25-build-e-deploy)
  - [2.6. Acesso Local](#26-acesso-local)
  - [2.7. Port Forwarding para Acesso LAN](#27-port-forwarding-para-acesso-lan)
  - [2.8. Parar e Reiniciar](#28-parar-e-reiniciar)
- [3. Cenário B — Servidor Linux Dedicado](#3-cenário-b--servidor-linux-dedicado)
  - [3.1. Pré-requisitos](#31-pré-requisitos)
  - [3.2. Instalação do Docker Engine](#32-instalação-do-docker-engine)
  - [3.3. Clonar e Configurar](#33-clonar-e-configurar)
  - [3.4. Build e Deploy](#34-build-e-deploy)
  - [3.5. Firewall](#35-firewall)
- [4. Pós-Implantação](#4-pós-implantação)
  - [4.1. Senhas Padrão](#41-senhas-padrão)
  - [4.2. Backup Automático](#42-backup-automático)
  - [4.3. Manutenção](#43-manutenção)
- [5. Troubleshooting](#5-troubleshooting)
- [6. Referências](#6-referências)

---

## 1. Visão Geral da Arquitetura

```
┌─────────────────────────────────────────────────────────────┐
│                     Servidor (Linux)                         │
│                                                              │
│  ┌─────────────────┐  ┌─────────────────┐                    │
│  │  cameras-app     │  │  visitantes-app │                    │
│  │  PHP 8.2 Apache  │  │  PHP 8.2 Apache │                    │
│  │  Porta 80        │  │  Porta 8080     │                    │
│  └────────┬─────────┘  └────────┬────────┘                    │
│           │                     │                             │
│  ┌────────▼─────────┐  ┌────────▼────────┐                    │
│  │  cameras-db      │  │  visitantes-db  │                    │
│  │  MySQL 8.0       │  │  MySQL 8.0      │                    │
│  │  Porta 3306      │  │  Porta 3307     │                    │
│  └──────────────────┘  └─────────────────┘                    │
│                              │                                │
│                     ┌───────▼────────┐                       │
│                     │  phpmyadmin    │                       │
│                     │  Porta 8081    │                       │
│                     └────────────────┘                       │
└─────────────────────────────────────────────────────────────┘
```

### Componentes

| Serviço | Container | Porta | Função |
|---------|-----------|-------|--------|
| Câmeras | `cameras-app` | 80 | Aplicação PHP Sistema de Câmeras |
| Câmeras DB | `cameras-db` | 3306 (interno) | MySQL 8.0 do Câmeras |
| Visitantes | `visitantes-app` | 8080 | Aplicação PHP Sistema de Visitantes |
| Visitantes DB | `visitantes-db` | 3307 | MySQL 8.0 do Visitantes |
| phpMyAdmin | `visitantes-pma` | 8081 | Gerenciamento MySQL via browser |

### Rede

- Cada projeto tem sua própria rede bridge Docker isolada
- Containers se comunicam pelo nome do serviço (ex: `db:3306`)
- Portas expostas no host para acesso externo

### Volumes Persistentes

| Volume | Montagem | Dados |
|--------|----------|-------|
| `sistema-cameras-v10_mysql_data` | `/var/lib/mysql` | Banco do Câmeras |
| `sistema-visitantes-v10_mysql_data` | `/var/lib/mysql` | Banco do Visitantes |
| `sistema-visitantes-v10_fotos_visitantes` | `/var/www/html/fotos_visitantes` | Fotos dos visitantes |

---

## 2. Cenário A — WSL2 (Desenvolvimento Local)

### 2.1. Pré-requisitos

- Windows 10/11 com WSL2 instalado
- Distribuição Linux na WSL (recomendado: Debian 12+ ou Ubuntu 22.04+)
- Acesso à internet para clonar repositórios e baixar imagens Docker

### 2.2. Instalação do Docker Engine no WSL2

**Não use Docker Desktop.** Instale o Docker Engine diretamente na distro WSL2.

Acesse o terminal WSL2:

```bash
# Baixar e executar o script oficial de instalação
curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
sudo sh /tmp/get-docker.sh
```

> O script detecta a distribuição, adiciona os repositórios oficiais e instala Docker Engine, containerd e Docker Compose plugin.

```bash
# Verificar instalação
sudo docker --version
# Exemplo: Docker version 29.5.3, build 62b7c3e

sudo docker compose version
# Exemplo: Docker Compose version v2.33.1
```

Adicionar seu usuário ao grupo `docker` para não precisar de `sudo`:

```bash
sudo usermod -aG docker $USER
```

**Reinicie o terminal WSL2** para o grupo fazer efeito. Verifique:

```bash
docker ps
# CONTAINER ID   IMAGE     COMMAND   CREATED   STATUS   PORTS   NAMES
```

### 2.3. Clonar Repositórios

```bash
sudo mkdir -p /opt
cd /opt

# Clonar sistema de câmeras
sudo git clone https://github.com/anomalyco/sistema-cameras-v1.0.git

# Clonar sistema de visitantes
sudo git clone https://github.com/anomalyco/sistema-visitantes-v1.0.git

# Ajustar permissões
sudo chown -R $USER:$USER /opt/sistema-cameras-v1.0 /opt/sistema-visitantes-v1.0
```

### 2.4. Configurar .env

Cada projeto precisa de um arquivo `.env` na raiz.

**sistema-cameras-v1.0/.env:**

```bash
cd /opt/sistema-cameras-v1.0
cp .env.example .env
nano .env
```

Conteúdo mínimo (substitua os valores):

```ini
CAMERAS_ENV=production
DB_HOST=db
DB_NAME=cftv_gml
DB_USER=cftv_user
DB_PASS=GERAR_SENHA_FORTE_AQUI
MYSQL_ROOT_PASS=GERAR_OUTRA_SENHA_FORTE_AQUI
APP_TIMEZONE=America/Sao_Paulo
APP_NAME=Sistema de Cameras e Alarmes
CAMERAS_SESSION_TIMEOUT=3600
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800
CAMERAS_CSP_ALLOW_INLINE_STYLES=0
APP_PORT=80
```

> Gere senhas seguras com: `openssl rand -base64 24`

**sistema-visitantes-v1.0/.env:**

```bash
cd /opt/sistema-visitantes-v1.0
cp .env.example .env
nano .env
```

Conteúdo:

```ini
VISITORPASS_DB_NAME=visitorpass
VISITORPASS_DB_USER=vpuser
VISITORPASS_DB_PASS=GERAR_SENHA_FORTE_AQUI
MYSQL_ROOT_PASSWORD=GERAR_OUTRA_SENHA_FORTE_AQUI
VISITORPASS_SESSION_TIMEOUT=3600
```

### 2.5. Build e Deploy

**Sistema de Câmeras:**

```bash
cd /opt/sistema-cameras-v1.0
docker compose build --pull
docker compose up -d
```

**Sistema de Visitantes:**

```bash
cd /opt/sistema-visitantes-v1.0
docker compose build --pull
docker compose up -d
```

**Verificar se os containers estão rodando:**

```bash
docker ps
```

Saída esperada (5 containers):

```
CONTAINER ID   IMAGE                                  PORTS                    NAMES
abc12345   visitantes-app   ...  0.0.0.0:8080->80/tcp   visitantes-app
def67890   cameras-app      ...  0.0.0.0:80->80/tcp     cameras-app
ghi11121   visitantes-db    ...  0.0.0.0:3307->3306/tcp  visitantes-db
jkl22232   cameras-db       ...  3306/tcp                cameras-db
mno33343   visitantes-pma   ...  0.0.0.0:8081->80/tcp   visitantes-pma
```

**Acompanhar logs durante o primeiro startup (especialmente MySQL e migration):**

```bash
docker compose logs -f
# Ctrl+C para sair
```

### 2.6. Acesso Local

| Sistema | URL |
|---------|-----|
| Sistema de Câmeras | http://localhost |
| Sistema de Visitantes | http://localhost:8080 |
| phpMyAdmin | http://localhost:8081 |

### 2.7. Port Forwarding para Acesso LAN

Para acessar os sistemas de outros computadores na rede local, crie um script PowerShell para configurar port forwarding no Windows.

**`scripts/port-forward.ps1`** (já incluso no repositório):

```powershell
# Execute como Administrador
$wslIp = (wsl -d Debian -- hostname -I).Trim().Split(' ')[0]

Write-Host "WSL2 IP: $wslIp" -ForegroundColor Cyan

netsh interface portproxy delete v4tov4 listenport=80   listenaddress=0.0.0.0 2>$null
netsh interface portproxy delete v4tov4 listenport=8080 listenaddress=0.0.0.0 2>$null
netsh interface portproxy delete v4tov4 listenport=8081 listenaddress=0.0.0.0 2>$null

netsh interface portproxy add v4tov4 listenport=80   listenaddress=0.0.0.0 connectport=80   connectaddress=$wslIp
netsh interface portproxy add v4tov4 listenport=8080 listenaddress=0.0.0.0 connectport=8080 connectaddress=$wslIp
netsh interface portproxy add v4tov4 listenport=8081 listenaddress=0.0.0.0 connectport=8081 connectaddress=$wslIp

# Firewall
New-NetFirewallRule -DisplayName "WSL2-Cameras-80"     -Direction Inbound -Protocol TCP -LocalPort 80   -Action Allow -ErrorAction SilentlyContinue
New-NetFirewallRule -DisplayName "WSL2-Visitantes-8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -ErrorAction SilentlyContinue
New-NetFirewallRule -DisplayName "WSL2-PMA-8081"       -Direction Inbound -Protocol TCP -LocalPort 8081 -Action Allow -ErrorAction SilentlyContinue
```

**Executar:**

1. Clique direito no arquivo > "Run with PowerShell (Admin)"
2. Ou execute no PowerShell como Admin:

```powershell
.\scripts\port-forward.ps1
```

**Verificar port forwarding:**

```powershell
netsh interface portproxy show all
```

Saída esperada:

```
Listen on ipv4:             Connect to ipv4:

Address         Port        Address         Port
--------------- ----------  --------------- ----------
0.0.0.0         80          172.18.116.113  80
0.0.0.0         8080        172.18.116.113  8080
0.0.0.0         8081        172.18.116.113  8081
```

**Encontrar seu IP na LAN:**

```powershell
ipconfig
# Procure por "Endereço IPv4" na placa de rede ativa
```

Acesse de outro computador na LAN: `http://<SEU_IP>:8080`

### 2.8. Parar e Reiniciar

```bash
cd /opt/sistema-cameras-v1.0
docker compose down    # Parar
docker compose up -d   # Iniciar

cd /opt/sistema-visitantes-v1.0
docker compose down
docker compose up -d
```

**Reconstruir após alterações no código:**

```bash
docker compose build --no-cache
docker compose up -d
```

> O container `visitantes-app` tem o código embutido na imagem Docker (cópia via `COPY . /var/www/html/` no Dockerfile). Alterações no código PHP exigem rebuild da imagem. O container `cameras-app` também embute o código.

---

## 3. Cenário B — Servidor Linux Dedicado

### 3.1. Pré-requisitos

- Servidor Linux (Debian 12+ ou Ubuntu 22.04+ recomendado)
- Acesso root ou usuário com sudo
- Portas 80, 8080, 8081 liberadas no firewall
- Git instalado

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y git curl
```

### 3.2. Instalação do Docker Engine

```bash
curl -fsSL https://get.docker.com -o /tmp/get-docker.sh
sudo sh /tmp/get-docker.sh
```

Verificar:

```bash
sudo docker --version
sudo docker compose version
```

Adicionar usuário ao grupo docker:

```bash
sudo usermod -aG docker $USER
# Faça logout e login novamente
```

### 3.3. Clonar e Configurar

```bash
sudo mkdir -p /opt
cd /opt
sudo git clone https://github.com/anomalyco/sistema-cameras-v1.0.git
sudo git clone https://github.com/anomalyco/sistema-visitantes-v1.0.git
sudo chown -R $USER:$USER /opt/sistema-cameras-v1.0 /opt/sistema-visitantes-v1.0
```

Criar arquivos `.env` (mesmo procedimento da [seção 2.4](#24-configurar-env)).

### 3.4. Build e Deploy

```bash
cd /opt/sistema-cameras-v1.0
docker compose build --pull
docker compose up -d

cd /opt/sistema-visitantes-v1.0
docker compose build --pull
docker compose up -d
```

### 3.5. Firewall

Liberar as portas no firewall do servidor:

**Com ufw (Ubuntu/Debian):**

```bash
sudo ufw allow 80/tcp
sudo ufw allow 8080/tcp
sudo ufw allow 8081/tcp
sudo ufw enable
```

**Com iptables:**

```bash
sudo iptables -A INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 8080 -j ACCEPT
sudo iptables -A INPUT -p tcp --dport 8081 -j ACCEPT
```

**Verificar portas ouvindo no servidor:**

```bash
sudo ss -tlnp | grep -E '(80|8080|8081)'
```

---

## 4. Pós-Implantação

### 4.1. Senhas Padrão

O `migrate.php` do Visitantes cria 3 usuários padrão na primeira execução:

| Usuário | Senha (primeiro acesso) | Nível |
|---------|------------------------|-------|
| `admin` | `Admin@2026Temp!` | admin |
| `supervisor` | `Supervisor@2026Temp!` | supervisor |
| `user` | `User@2026Temp!` | user |

> ⚠️ **Na primeira vez que cada usuário fizer login, o sistema exigirá a troca de senha.** Use senhas fortes e diferentes.

O sistema de Câmeras gerencia seus próprios usuários internamente.

**Alterar senha de um usuário pelo banco (caso necessário):**

```bash
# Conectar no container do MySQL visitantes
docker exec -it visitantes-db mysql -u root -p visitantes-db -p
# Digite a senha root (definida no .env como MYSQL_ROOT_PASSWORD)
```

```sql
UPDATE usuarios SET senha = '$2y$10$...', senha_temporaria = 1 WHERE usuario = 'admin';
```

Para gerar o hash bcrypt para usar no SQL acima, execute:

```bash
# Usando PHP dentro do container
docker exec visitantes-app php -r "echo password_hash('NovaSenhaAqui', PASSWORD_BCRYPT, ['cost' => 10]);"
```

### 4.2. Backup Automático

O script de backup em `/opt/backup.sh` deve ser configurado manualmente (não faz parte dos repositórios git).

**Criar `/opt/backup.sh`:**

```bash
#!/bin/bash
set -euo pipefail

BACKUP_DIR="/opt/backups"
RETENTION_DAYS=7
DATE=$(date +%Y%m%d_%H%M%S)
LOG_FILE="${BACKUP_DIR}/backup.log"

mkdir -p "${BACKUP_DIR}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "${LOG_FILE}"
}

log "=== Iniciando backup ==="

# MySQL dump cameras
log "Dump cameras-db..."
docker exec cameras-db mysqldump -u root -pSENHA_ROOT_CAMERAS \
  --all-databases --single-transaction --routines --triggers --events 2>/dev/null | \
  gzip > "${BACKUP_DIR}/cameras-db_${DATE}.sql.gz"

# MySQL dump visitantes
log "Dump visitantes-db..."
docker exec visitantes-db mysqldump -u root -pSENHA_ROOT_VISITANTES \
  --all-databases --single-transaction --routines --triggers --events 2>/dev/null | \
  gzip > "${BACKUP_DIR}/visitantes-db_${DATE}.sql.gz"

# Volume fotos_visitantes
log "Backup fotos_visitantes..."
docker run --rm \
  -v sistema-visitantes-v10_fotos_visitantes:/data \
  -v "${BACKUP_DIR}:/backup" \
  alpine tar czf "/backup/fotos_visitantes_${DATE}.tar.gz" -C /data .

# Cleanup old backups
find "${BACKUP_DIR}" \( -name "*.sql.gz" -o -name "*.tar.gz" \) -type f -mtime +${RETENTION_DAYS} -delete -print

log "=== Backup concluido ==="
echo "Backup salvo em: ${BACKUP_DIR}"
```

> Substitua `SENHA_ROOT_CAMERAS` e `SENHA_ROOT_VISITANTES` pelas senhas definidas nos respectivos `.env`.

```bash
chmod +x /opt/backup.sh
```

**Agendar no cron (execução diária às 3h):**

```bash
sudo crontab -e
```

Adicionar linha:

```
0 3 * * * /opt/backup.sh
```

**Restaurar backup MySQL:**

```bash
# Cameras
gunzip -c /opt/backups/cameras-db_YYYYMMDD_HHMMSS.sql.gz | docker exec -i cameras-db mysql -u root -pSENHA_ROOT

# Visitantes
gunzip -c /opt/backups/visitantes-db_YYYYMMDD_HHMMSS.sql.gz | docker exec -i visitantes-db mysql -u root -pSENHA_ROOT
```

**Restaurar fotos:**

```bash
docker run --rm \
  -v sistema-visitantes-v10_fotos_visitantes:/data \
  -v "/opt/backups:/backup" \
  alpine tar xzf "/backup/fotos_visitantes_YYYYMMDD_HHMMSS.tar.gz" -C /data
```

### 4.3. Manutenção

**Logs dos containers:**

```bash
# Ver logs em tempo real
docker logs -f visitantes-app
docker logs -f cameras-app

# Últimas 50 linhas
docker logs --tail 50 visitantes-app
```

**Acessar o container (debug):**

```bash
docker exec -it visitantes-app bash
docker exec -it cameras-app bash
```

**Atualizar containers com novas imagens (após `git pull`):**

```bash
cd /opt/sistema-visitantes-v1.0
git pull
docker compose build --no-cache
docker compose up -d

cd /opt/sistema-cameras-v1.0
git pull
docker compose build --no-cache
docker compose up -d
```

**Verificar saúde dos containers:**

```bash
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
```

**Inspecionar rede:**

```bash
docker network ls
docker network inspect sistema-visitantes-v10_visitantes-net
```

**Limpar imagens e volumes não utilizados:**

```bash
docker system prune -f
docker volume prune -f
```

---

## 5. Troubleshooting

### 5.1. Container não sobe

**Sintoma:** `docker ps` não mostra o container esperado.

**Diagnóstico:**

```bash
docker logs visitantes-app
docker compose ps
```

**Causa comum:** MySQL não ficou pronto a tempo.

O entrypoint aguarda o MySQL ficar acessível antes de prosseguir. Se o MySQL demorar muito no primeiro startup (criando banco de dados), o healthcheck pode falhar.

**Solução:** Aguarde mais tempo e verifique novamente:

```bash
docker compose logs -f db
# Aguarde até ver "ready for connections"
```

### 5.2. Login falha / senha não reconhecida

**Sintoma:** Ao fazer login, o sistema retorna para a página de login sem mensagem de erro.

**Diagnóstico:**

1. Verifique se o usuário existe no banco:

```bash
docker exec visitantes-db mysql -u root -p visitantes -e "SELECT usuario, LENGTH(senha) as hash_len, senha_temporaria FROM usuarios;"
```

2. Verifique se o hash da senha é um hash bcrypt válido (deve começar com `$2y$10$` e ter 60 caracteres). Se aparecer texto plano ou comprimento diferente de 60, a senha foi corrompida.

3. Teste a senha via PHP:

```bash
docker exec visitantes-app php -r "
\$pdo = new PDO('mysql:host=db;dbname=visitantes', 'vpuser', 'senha_do_env');
\$r = \$pdo->query(\"SELECT senha FROM usuarios WHERE usuario='admin'\")->fetch();
echo password_verify('senha_tentativa', \$r[0]) ? 'OK' : 'FAIL';
"
```

**Causa comum 1 — BOM UTF-8:** Arquivos PHP podem conter BOM UTF-8 (`EF BB BF`) no início, que impede o funcionamento de `session_start()` e `header()`. Todos os redirects falham silenciosamente.

**Solução:** Verificar e remover BOM:

```bash
# Detectar BOM
grep -rl $'\xEF\xBB\xBF' /opt/sistema-visitantes-v1.0/public/*.php /opt/sistema-visitantes-v1.0/scripts/*.php

# Remover BOM
find /opt/sistema-visitantes-v1.0 -name "*.php" -exec sed -i '1s/^\xEF\xBB\xBF//' {} \;
```

**Causa comum 2 — Output antes de header/redirect:** Qualquer saída antes de `header('Location: ...')` impede o redirect. O código deve ter `ob_start()` no início do `index.php` principal.

**Verificação:**

```bash
head -1 /opt/sistema-visitantes-v1.0/public/index.php
# Deve conter: <?php ob_start();
```

**Causa comum 3 — Hash sobrescrito por migration:** O `docker/entrypoint.sh` executa `docker/migrate.php` toda vez que o container inicia. O migrate.php cria usuários APENAS se eles não existirem (`SELECT COUNT(*)`). Portanto, não sobrescreve senhas existentes. Se o hash foi alterado para texto plano, foi por outra causa (ex: alteração manual no banco).

**Solução:** Resetar a senha corretamente:

```bash
docker exec visitantes-app php -r "
\$pdo = new PDO('mysql:host=db;port=3306;charset=utf8mb4', 'vpuser', 'senha_do_env');
\$pdo->exec('USE visitantes');
\$hash = password_hash('NovaSenhaAqui', PASSWORD_BCRYPT, ['cost' => 10]);
\$pdo->prepare('UPDATE usuarios SET senha = :h, senha_temporaria = 1 WHERE usuario = :u')
    ->execute([':h' => \$hash, ':u' => 'admin']);
echo 'Senha resetada com sucesso\\n';
"
```

### 5.3. Erro de conexão MySQL

**Sintoma:** Página branca ou erro "Connection refused".

**Diagnóstico:**

```bash
docker logs visitantes-app
docker logs visitantes-db
docker exec visitantes-app php -r "
try {
    new PDO('mysql:host=db;port=3306', 'vpuser', 'senha');
    echo 'OK';
} catch (Exception \$e) {
    echo \$e->getMessage();
}
"
```

**Causa:** Container `db` não está rodando, ou as credenciais no `.env` não correspondem.

**Solução:**

```bash
# Verificar se o banco está rodando
docker ps | grep db

# Reconstruir e iniciar
docker compose down
docker compose up -d
```

### 5.4. Página em branco (WSOD)

**Sintoma:** Tela branca ao acessar qualquer página.

**Diagnóstico:**

```bash
docker logs visitantes-app
```

**Causa comum:** Erro PHP não exibido. Ative o display_errors temporariamente para debug:

```bash
docker exec -it visitantes-app bash -c "echo 'display_errors = On' >> /usr/local/etc/php/conf.d/custom.ini && /etc/init.d/apache2 reload"
```

### 5.5. Permissão negada em fotos_visitantes

**Sintoma:** Erro ao fazer upload de fotos.

**Solução:**

```bash
docker exec visitantes-app chown -R www-data:www-data /var/www/html/fotos_visitantes
docker exec visitantes-app chmod 755 /var/www/html/fotos_visitantes
```

### 5.6. Port forwarding parou de funcionar (WSL2)

**Sintoma:** Sistemas acessíveis via localhost mas não via IP da LAN.

**Causa:** O IP do WSL2 mudou após reboot.

**Solução:**

```powershell
# Execute novamente como Administrador
.\scripts\port-forward.ps1
```

### 5.7. phpMyAdmin não acessível

**Sintoma:** http://localhost:8081 não carrega.

**Diagnóstico:**

```bash
docker logs visitantes-pma
docker compose ps
```

**Causa:** Container phpMyAdmin depende do `db` mas pode iniciar antes dele. Reinicie:

```bash
docker compose restart phpmyadmin
```

---

## 6. Referências

- [Docker Engine installation](https://docs.docker.com/engine/install/)
- [Docker Compose file reference](https://docs.docker.com/compose/compose-file/)
- [PHP: password_hash](https://www.php.net/manual/en/function.password-hash.php)
- [MySQL 8.0 Docker image](https://hub.docker.com/_/mysql)
- [phpMyAdmin Docker image](https://hub.docker.com/_/phpmyadmin)
- [WSL2 port forwarding](https://learn.microsoft.com/en-us/windows/wsl/networking)
