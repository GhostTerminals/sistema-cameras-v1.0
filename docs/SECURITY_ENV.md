# 🔒 Guia de Segurança - Configuração de Ambiente

## ⚠️ IMPORTANTE: .env com Credenciais Expostas

Se você clonou este repositório e viu um arquivo `.env` com credenciais reais, ele foi acidentalmente commitado. 

### Remover .env do Git (sem perder o arquivo local)

```bash
# Parar de rastrear o arquivo
git rm --cached .env
git commit -m "Remove .env from version control"

# Verificar que foi removido do histórico futuro
git status  # .env não deve aparecer mais
```

### Se o arquivo está em todo o histórico Git (risco!)

```bash
# OPÇÃO 1: Usar BFG (mais rápido)
bfg --delete-files .env --replace-refs DELETE_FILES_REFS_NONE

# OPÇÃO 2: Usar git filter-branch (mais lento, mas padrão)
git filter-branch --tree-filter 'rm -f .env' -f HEAD
```

## ✅ Configuração Correta

### 1. Em Desenvolvimento Local

```bash
# Copiar template
cp .env.template .env

# Editar com suas credenciais
nano .env  # ou seu editor preferido
```

### 2. Em Produção (Docker)

```bash
# Criar arquivo .env com senhas fortes
DB_PASS=$(openssl rand -base64 32)
MYSQL_ROOT_PASS=$(openssl rand -base64 32)

# Salvar em local seguro (não no repositório!)
# Exemplo: /etc/sistema-cameras/.env

# Usar no docker-compose
docker compose --env-file /etc/sistema-cameras/.env up -d
```

### 3. Em CI/CD (GitHub Actions)

```yaml
# No secrets do repositório, adicionar:
# - DB_USER
# - DB_PASS
# - MYSQL_ROOT_PASS

env:
  DB_USER: ${{ secrets.DB_USER }}
  DB_PASS: ${{ secrets.DB_PASS }}
  MYSQL_ROOT_PASS: ${{ secrets.MYSQL_ROOT_PASS }}
```

## 📋 Checklist de Segurança

- [ ] `.env` está no `.gitignore`
- [ ] `.env` não foi commitado (verificar com `git log .env`)
- [ ] `.env.example` ou `.env.template` está no repositório
- [ ] `.env` local tem senhas diferentes para cada ambiente
- [ ] Em produção, `.env` está em local seguro (fora do web root)
- [ ] Em CI/CD, credenciais estão em Secrets, não em código
- [ ] Credenciais de banco têm acesso limitado (não root em produção)

## 🔑 Gerando Senhas Seguras

```bash
# Linux/Mac/WSL
openssl rand -base64 32

# PowerShell
[Convert]::ToBase64String((1..32 | ForEach-Object {Get-Random -Maximum 256}) -as [byte[]])
```

## Variáveis de Ambiente

| Variável | Descrição | Exemplo |
|----------|-----------|---------|
| `CAMERAS_ENV` | Ambiente de execução | `development`, `testing`, `production` |
| `DB_HOST` | Host do banco de dados | `localhost`, `db` (Docker) |
| `DB_NAME` | Nome do banco | `cftv_gml` |
| `DB_USER` | Usuário do banco | `cftv_user` |
| `DB_PASS` | Senha do banco | Mínimo 32 caracteres aleatórios |
| `MYSQL_ROOT_PASS` | Senha root MySQL | Mínimo 32 caracteres aleatórios |
| `APP_TIMEZONE` | Timezone da aplicação | `America/Sao_Paulo` |
| `CAMERAS_SESSION_TIMEOUT` | Timeout de sessão (segundos) | `3600` (1 hora) |
| `CAMERAS_SESSION_ABSOLUTE_TIMEOUT` | Timeout absoluto (segundos) | `28800` (8 horas) |
| `CAMERAS_CSP_ALLOW_INLINE_STYLES` | Permitir inline CSS | `0` (recomendado), `1` (Bootstrap) |
| `APP_PORT` | Porta HTTP | `80` (reverse proxy), `8080` (direto) |

---

**Última atualização**: 2026-06-01  
**Versão**: 1.0
