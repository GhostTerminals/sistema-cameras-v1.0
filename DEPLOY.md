# 🚀 Guia de Deployment - Produção com Docker

**Projeto**: Sistema de Câmeras e Alarmes v1.0  
**Ambiente**: Intranet (Linux Server)  
**Data**: 29/05/2026

---

## 📋 Pré-requisitos

- **OS**: Ubuntu Server 22.04+ ou Debian 12+
- **Docker Engine**: v24.0+
- **Docker Compose**: v2.0+
- **Acesso root**: Necessário para instalação
- **Espaço em disco**: Mínimo 5GB livres

### Verificar pré-requisitos

```bash
docker --version        # Deve ser 24.0+
docker compose version  # Deve ser v2+
df -h                   # Verificar espaço
```

### Instalar Docker (se necessário)

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y docker.io docker-compose-v2 git curl

# Ativar serviço
sudo systemctl enable --now docker

# Adicionar user ao grupo docker (opcional, para não usar sudo)
sudo usermod -aG docker $USER
newgrp docker
```

---

## 🔧 Phase 1: Preparação (10 minutos)

### 1.1 Clonar/Copiar Projeto

```bash
# Opção A: Clonar do Git
git clone <seu-repositorio> /opt/sistema-cameras
cd /opt/sistema-cameras

# Opção B: Copiar arquivos
mkdir -p /opt/sistema-cameras
cd /opt/sistema-cameras
# Copiar arquivos aqui
```

### 1.2 Gerar Senhas Seguras

```bash
# Gerar DB_PASS (32 caracteres)
DB_PASS=$(openssl rand -base64 32)
echo "DB_PASS=$DB_PASS"

# Gerar MYSQL_ROOT_PASS (32 caracteres)
MYSQL_ROOT_PASS=$(openssl rand -base64 32)
echo "MYSQL_ROOT_PASS=$MYSQL_ROOT_PASS"

# Salvar em local seguro (ex: KeePass, 1Password)
```

### 1.3 Criar Arquivo `.env`

> ⚠️ **Segurança**: Coloque o `.env` em local seguro fora do projeto:
> - Linux: `/etc/sistema-cameras/.env` (recomendado)
> - Windows: `C:\env\sistema-cameras\.env` (recomendado)
> - Ou mantenha na raiz do projeto como fallback

```bash
# OPCAO A: Local seguro externo (recomendado)
sudo mkdir -p /etc/sistema-cameras
sudo tee /etc/sistema-cameras/.env > /dev/null <<EOF
CAMERAS_ENV=production
DB_HOST=db
DB_NAME=cftv_gml
DB_USER=cftv_user
DB_PASS=$DB_PASS
MYSQL_ROOT_PASS=$MYSQL_ROOT_PASS
APP_TIMEZONE=America/Sao_Paulo
CAMERAS_SESSION_TIMEOUT=3600
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800
CAMERAS_CSP_ALLOW_INLINE_STYLES=0
EOF

# Proteger permissoes
sudo chmod 600 /etc/sistema-cameras/.env
sudo chown -R www-data:www-data /etc/sistema-cameras/

# OPCAO B: Na raiz do projeto (apenas se a DocumentRoot for public/)
cat > .env <<EOF
CAMERAS_ENV=production
DB_HOST=db
DB_NAME=cftv_gml
DB_USER=cftv_user
DB_PASS=$DB_PASS
MYSQL_ROOT_PASS=$MYSQL_ROOT_PASS
APP_TIMEZONE=America/Sao_Paulo
CAMERAS_SESSION_TIMEOUT=3600
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800
CAMERAS_CSP_ALLOW_INLINE_STYLES=0
EOF

# Proteger permissoes
chmod 600 .env
```

### 1.4 Verificar Estrutura

```bash
ls -la
# Deve conter:
# - Dockerfile
# - docker-compose.yml
# - .env (criado acima)
# - .dockerignore
# - config/
# - public/
# - src/
# ... etc
```

---

## 🏗️ Phase 2: Build da Imagem (5 minutos)

### 2.1 Build

```bash
docker compose build
```

**Esperado**: Sem erros, imagem criada com sucesso

### 2.2 Verificar Imagem

```bash
docker images | grep cameras
# Deve listar: cameras-app  latest  <ID>  <TAMANHO>
```

---

## 🚀 Phase 3: Iniciar Containers (5 minutos)

### 3.1 Começar com Docker Compose

```bash
docker compose up -d
```

**Esperado**: 
```
[+] Running 2/2
 ✔ Container cameras-db   Healthy  0.2s
 ✔ Container cameras-app  Running  1.5s
```

### 3.2 Aguardar Healthchecks

```bash
# Acompanhar status em tempo real
watch -n 2 'docker compose ps'

# Esperado após ~30s:
# cameras-db    ... healthy
# cameras-app   ... healthy
```

### 3.3 Verificar Logs

```bash
# Ver logs da aplicação
docker compose logs app

# Ver logs do banco
docker compose logs db

# Acompanhar em tempo real
docker compose logs -f app
```

---

## 🔒 Phase 3.5: HTTPS com Nginx (5 minutos)

> Opcional, mas **fortemente recomendado** para produção. Sem HTTPS, cookies de sessão trafegam sem o flag Secure.

### 3.5.1 Gerar Certificados

```bash
# Opção A: Auto-assinado (intranet)
bash nginx/generate-certs.sh

# Opção B: Let's Encrypt (com domínio público)
sudo apt install -y certbot
sudo certbot certonly --standalone -d seu-dominio.com
sudo cp /etc/letsencrypt/live/seu-dominio.com/fullchain.pem nginx/certs/cert.pem
sudo cp /etc/letsencrypt/live/seu-dominio.com/privkey.pem nginx/certs/key.pem
sudo chmod 600 nginx/certs/key.pem
```

### 3.5.2 Iniciar com Proxy

```bash
# Parar containers se estiverem rodando
docker compose down

# Iniciar com HTTPS via Nginx
docker compose -f docker-compose.yml -f docker-compose.https.yml up -d
```

### 3.5.3 Verificar

```bash
# Testar redirecionamento HTTP → HTTPS
curl -I http://localhost/  # Deve retornar 301

# Testar HTTPS
curl -k https://localhost/index.php?page=api/api_health
```

---

## ✅ Phase 4: Validação (10 minutos)

### 4.1 Health Check HTTP

```bash
# Deve retornar 200 ou 401 (sem sessão)
curl -I http://localhost/index.php?page=api/api_health
# Expected: HTTP/1.1 200 OK ou 401 Unauthorized
```

### 4.2 Teste de Conexão com Banco

```bash
# Entrar no container app
docker exec -it cameras-app bash

# Dentro do container, testar conexão
php -r "
require 'config/config.php';
\$db = new database();
echo 'Conexão OK';
"

# Sair
exit
```

### 4.3 Teste de Login

Abrir no navegador:
```
http://localhost/index.php?page=login
```

**Esperado**: Página de login carrega normalmente

**Usuários padrão**:
| Usuário | Senha | Ação |
|---------|-------|------|
| `admin` | temporária | Trocar na 1ª vez |
| `supervisor` | temporária | Trocar na 1ª vez |
| `user` | temporária | Trocar na 1ª vez |

> **Importante**: Alterar todas as senhas temporárias no primeiro login!

### 4.4 Teste de Câmeras (CRUD)

1. Login com `admin`
2. Navegar para "Câmeras"
3. Testar: Criar, Editar, Listar, Deletar
4. Confirmar que funciona normalmente

### 4.5 Teste de Relatórios

1. Navegar para "Relatórios"
2. Gerar "Relatório de Câmeras"
3. Verificar se dados aparecem

---

## 🔒 Phase 5: Segurança Pós-Deploy (5 minutos)

### 5.1 Verificar Arquivo `.env`

```bash
# Confirmar que não foi commitado
git status .env  # Não deve aparecer

# Verificar permissões
ls -l .env       # Deve ser: -rw------- (600)
```

### 5.2 Limpar Arquivos Sensíveis do Container

```bash
# Verificar se não existem cópias de .env dentro do container
docker exec cameras-app ls -la .env  # Deve retornar "file not found"
```

### 5.3 Verificar Logs Estão Vindo

```bash
# Ver logs de acesso Apache
docker compose logs app | grep "200\|302\|404"
```

---

## 📊 Phase 6: Monitoramento Contínuo

### 6.1 Comando de Verificação Diária

```bash
#!/bin/bash
# Salvar como: /opt/check-prod.sh

echo "=== STATUS DOS CONTAINERS ==="
docker compose ps

echo "=== HEALTHCHECKS ==="
docker inspect --format='{{.State.Health.Status}}' cameras-db
docker inspect --format='{{.State.Health.Status}}' cameras-app

echo "=== TESTE DE CONEXÃO HTTP ==="
curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" \
    http://localhost/index.php?page=api/api_health

echo "=== VERIFICAÇÃO DE LOGS ==="
docker compose logs --tail=20 | grep -E "ERROR|WARN" || echo "Sem erros recentes"
```

Executar diariamente:
```bash
chmod +x /opt/check-prod.sh
/opt/check-prod.sh
```

### 6.2 Backup Automático do Banco

```bash
# Criar diretório de backups
mkdir -p /backups

# Adicionar ao crontab para backup diário às 2:00 AM
crontab -e

# Adicionar linha:
0 2 * * * docker exec cameras-db mysqldump -u root -p"MYSQL_ROOT_PASS" \
    --single-transaction cftv_gml > /backups/cftv_gml_$(date +\%Y\%m\%d).sql

# Substituir MYSQL_ROOT_PASS pelo valor real
```

### 6.3 Rotação de Backups (manter últimos 30 dias)

```bash
# Adicionar ao crontab:
0 3 * * * find /backups -name "*.sql" -mtime +30 -delete
```

---

## 🛠️ Comandos Úteis do Dia a Dia

### Ver Status

```bash
docker compose ps
docker compose logs -f app
```

### Reiniciar Containers

```bash
# Restart completo
docker compose restart

# Rebuildar e reiniciar (após mudanças de código)
docker compose up -d --build

# Parar sem remover volumes
docker compose down
```

### Acessar Containers

```bash
# Shell PHP/Apache
docker exec -it cameras-app bash

# MySQL CLI
docker exec -it cameras-db mysql -u root -p

# Executar comando PHP direto
docker exec cameras-app php -r "code aqui"
```

### Logs

```bash
# Apache access
docker exec cameras-app tail -f /var/log/apache2/access.log

# Apache errors
docker exec cameras-app tail -f /var/log/apache2/error.log

# MySQL errors
docker compose logs db
```

### Limpar

```bash
# Parar e remover containers (keep data)
docker compose down

# Parar e DELETAR tudo (cuidado!)
docker compose down -v
```

---

## ⚠️ Troubleshooting

| Problema | Causa | Solução |
|----------|-------|--------|
| **Port 80 já em uso** | Outro serviço ocupando | `sudo lsof -i :80` e parar serviço |
| **MySQL não fica healthy** | Permissão ou corrupção | `docker compose logs db` |
| **App retorna erro 500** | Erro PHP ou DB | `docker compose logs app` |
| **Conexão DB recusada** | Host/senha errada | Verificar `.env` |
| **Containers não iniciam** | Falta de espaço | `df -h` e limpar `docker system prune` |
| **Permissão negada ao .env** | Arquivo com chmod errado | `chmod 600 .env` |

---

## 🔄 Atualizar Código (Após Mudanças)

```bash
# 1. Parar containers
docker compose down

# 2. Atualizar código (git pull ou copiar novos arquivos)
git pull

# 3. Rebuildar imagem
docker compose build

# 4. Reiniciar
docker compose up -d

# 5. Verificar
docker compose logs app
```

---

## 📞 Suporte e Referências

### Arquivos de Referência
- `.env.example` - Template de variáveis
- `config/app.php` - Configuração da aplicação
- `docker-compose.yml` - Definição dos containers
- `Dockerfile` - Definição da imagem

### Documentação Oficial
- [Docker Compose Official Docs](https://docs.docker.com/compose/)
- [Docker Best Practices](https://docs.docker.com/develop/dev-best-practices/)
- [MySQL in Docker](https://hub.docker.com/_/mysql)

### Checklist Pré-Production

- [ ] `.env` criado com senhas fortes
- [ ] `.env` com permissões 600
- [ ] `.env` não commitado no git
- [ ] Todos os containers em "healthy"
- [ ] Login funciona
- [ ] CRUD câmeras testado
- [ ] Relatórios testados
- [ ] Backup automático configurado
- [ ] Logs sendo capturados
- [ ] Plano de recuperação documentado

---

## ✅ Deployment Concluído!

Se chegou até aqui com sucesso, parabéns! 🎉

O sistema está **pronto para produção** em ambiente Intranet.

**Próximos passos opcionais:**
- Setup Nginx como reverse proxy para HTTPS
- Configurar alertas de healthcheck
- Integração com Zabbix/Prometheus para monitoring avançado

