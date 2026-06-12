# Sistema de Gerenciamento de Câmeras e Alarmes v1.0

[![CI/CD Pipeline](https://github.com/YOUR_ORG/sistema-cameras/workflows/CI%2FCD%20Pipeline/badge.svg)](https://github.com/YOUR_ORG/sistema-cameras/actions)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-blue)](https://www.php.net/)
[![License MIT](https://img.shields.io/badge/License-MIT-green)](LICENSE)

Sistema web de gerenciamento centralizado de câmeras e alarmes para ambientes empresariais (intranet).

## 📋 Índice

- [Características](#características)
- [Requisitos](#requisitos)
- [Instalação](#instalação)
- [Configuração](#configuração)
- [Uso](#uso)
- [Desenvolvimento](#desenvolvimento)
- [Testes](#testes)
- [Segurança](#segurança)
- [Troubleshooting](#troubleshooting)
- [Contribuição](#contribuição)

## ✨ Características

- ✅ **Gerenciamento de Câmeras**: Cadastro, edição, exclusão e monitoramento de câmeras
- ✅ **Gerenciamento de Alarmes**: Configuração e histórico de disparos
- ✅ **Auditoria Completa**: Rastreamento de todas as operações
- ✅ **Autenticação Segura**: Bcrypt + CSRF protection + Rate limiting
- ✅ **API RESTful v2**: Endpoints modernos e consistentes
- ✅ **Docker Ready**: Deploy fácil com Docker Compose
- ✅ **CI/CD Pipeline**: Testes automatizados com GitHub Actions

## 🛠️ Requisitos

### Desenvolvimento Local
- **PHP 8.1+** com extensões:
  - `pdo_mysql`
  - `json`
  - `session`
  - `fileinfo` (para upload validation)
- **MySQL 8.0+** ou **MariaDB 10.5+**
- **Composer** (para dependências PHP)
- **Node.js 16+** (opcional, para frontend)

### Produção
- **Docker 24.0+**
- **Docker Compose 2.0+**
- **Ubuntu Server 22.04+** ou **Debian 12+**

## 📦 Instalação

### Opção 1: Docker (Recomendado)

```bash
# Clonar repositório
git clone <seu-repositorio> sistema-cameras
cd sistema-cameras

# Copiar e configurar .env
cp .env.template .env
# Editar .env com suas credenciais
nano .env

# Iniciar containers
docker compose up -d

# Aguardar setup do banco (30-60 segundos)
docker compose logs -f app

# Acessar
# http://localhost:8080
```

### Opção 2: Instalação Local

```bash
# 1. Clonar
git clone <seu-repositorio> sistema-cameras
cd sistema-cameras

# 2. Instalar dependências PHP
composer install

# 3. Configurar ambiente
cp .env.template .env
# Editar .env com credenciais do MySQL local
nano .env

# 4. Criar banco de dados
mysql -uroot -p < config/DB/cftv_gml.sql

# 5. Iniciar servidor (para testes)
php -S localhost:8080 -t public/

# 6. Acessar
# http://localhost:8080
```

## ⚙️ Configuração

### Variáveis de Ambiente

Copie `.env.template` para `.env` e configure:

```bash
# Banco de Dados
DB_HOST=localhost           # Host do MySQL
DB_NAME=cftv_gml           # Nome do banco
DB_USER=cftv_user          # Usuário (não root em produção)
DB_PASS=seu_senha_aqui     # Senha segura (min 32 chars)

# Ambiente
CAMERAS_ENV=development    # development|testing|production

# Sessão
CAMERAS_SESSION_TIMEOUT=3600              # 1 hora
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800    # 8 horas

# Segurança
CAMERAS_CSP_ALLOW_INLINE_STYLES=0         # 0=strict, 1=permissivo
```

### Gerando Senhas Seguras

```bash
# Linux/Mac/WSL
openssl rand -base64 32

# PowerShell
[Convert]::ToBase64String((1..32 | ForEach-Object {Get-Random -Maximum 256}) -as [byte[]])
```

### Usando com Reverse Proxy (NGINX)

```nginx
upstream app {
    server app:80;
}

server {
    listen 443 ssl http2;
    server_name cameras.empresa.com;

    ssl_certificate /etc/letsencrypt/live/cameras.empresa.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/cameras.empresa.com/privkey.pem;

    location / {
        proxy_pass http://app;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}
```

## 🚀 Uso

### Login

- URL: `http://localhost:8080/`
- Usuário padrão: (criado durante setup)
- Senha: (definida durante setup)

### API v2 Endpoints

```bash
# Health check
curl http://localhost:8080/index.php?page=api/v2/api_health

# Listar câmeras (requer autenticação)
curl -H "X-CSRF-Token: <token>" http://localhost:8080/index.php?page=api/v2/api_cameras

# Criar câmera
curl -X POST \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: <token>" \
  -d '{"nome":"Câmera 1","ip":"192.168.1.100"}' \
  http://localhost:8080/index.php?page=api/v2/api_cadastrar_cameras
```

### Rate Limiting

- **Login**: 5 tentativas por 15 minutos por IP
- **Upload**: Tamanho máximo 10MB
- **API geral**: Configurável via `RateLimiter.php`

## 👨‍💻 Desenvolvimento

### Estrutura do Projeto

```
sistema-cameras/
├── api/
│   ├── RateLimiter.php      # Rate limiting
│   ├── ApiResponse.php      # Respostas padronizadas
│   ├── RequestValidator.php # Validação de entrada
│   ├── v2/                  # API endpoints v2
│   └── ...
├── auth/                    # Autenticação
├── config/                  # Configuração
│   ├── app.php
│   ├── database.php
│   └── DB/
│       └── cftv_gml.sql
├── docs/                    # Documentação
├── inc/                     # Includes comuns
│   ├── security.php         # Hash, CSRF, policies
│   └── single_session.php   # Gerencio de sessão
├── public/                  # Web root
│   ├── index.php
│   ├── css/
│   ├── js/
│   └── uploads/
├── src/                     # Classes PHP
│   ├── ErrorHandler.php
│   └── Exceptions.php
├── tests/                   # Testes automatizados
│   ├── Unit/
│   ├── Integration/
│   └── Api/
└── docker-compose.yml
```

### Adicionando um Novo Endpoint

1. Criar arquivo em `api/v2/api_seu_endpoint.php`:

```php
<?php
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ApiResponse::error('BAD_REQUEST', 'Apenas GET permitido');
    }

    configureSessionSecurity();
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario'])) {
        ApiResponse::unauthorized();
    }

    // Sua lógica aqui
    $data = ['exemplo' => 'valor'];
    ApiResponse::success($data, 'Sucesso');

} catch (Throwable $e) {
    error_log($e->getMessage());
    ApiResponse::internalError();
}
```

2. Registrar rota em `public/index.php`:

```php
$page = $_GET['page'] ?? 'dashboard';
$allowedPages = [
    // ...
    'api/v2/api_seu_endpoint' => 'api/v2/api_seu_endpoint.php',
];
```

## ✅ Testes

### Rodar Todos os Testes

```bash
# Com Docker
docker compose exec app composer test

# Local
vendor/bin/phpunit

# Com cobertura
vendor/bin/phpunit --coverage-text
```

### Testes Disponíveis

- **Unit Tests**: `tests/Unit/` - Funções isoladas
- **Integration Tests**: `tests/Integration/` - APIs e banco de dados
- **Security Tests**: `tests/Unit/SecurityTest.php` - Hash, CSRF, políticas

### Escrever Novo Teste

```php
<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

class MeuTesteTest extends TestCase
{
    public function testAlgo(): void
    {
        $resultado = minhaFuncao();
        $this->assertTrue($resultado);
    }
}
```

## 🔒 Segurança

### Medidas Implementadas

- ✅ **Senhas**: Bcrypt (PASSWORD_BCRYPT)
- ✅ **CSRF**: Tokens por sessão
- ✅ **Rate Limiting**: Proteção contra brute force
- ✅ **SQL Injection**: Prepared statements
- ✅ **Upload Validation**: MIME type check + size limit
- ✅ **Session Security**: HttpOnly, SameSite=Lax
- ✅ **CSP**: Content Security Policy

### Boas Práticas

1. **Nunca commite .env** com credenciais reais
2. **Use HTTPS em produção** (reverse proxy com Let's Encrypt)
3. **Atualize dependências regularmente**: `composer update`
4. **Monitore logs** em produção
5. **Faça backups** do banco de dados diariamente
6. **Restrinja acesso ao painel** por IP se possível

### Reportar Vulnerabilidades

Por favor, envie um email para `security@empresa.com` em vez de abrir uma issue pública.

## 🐛 Troubleshooting

### Erro de Conexão com Banco

```bash
# Verificar MySQL está rodando
docker compose ps

# Ver logs
docker compose logs db

# Testar conexão
docker compose exec db mysql -uroot -p$MYSQL_ROOT_PASS -e "SELECT 1"
```

### Erro de Permissão em Uploads

```bash
# Verificar permissões
docker compose exec app ls -la public/uploads/

# Ajustar permissões
docker compose exec app chmod 755 public/uploads/
```

### Erro 500 na API

```bash
# Ver logs da aplicação
docker compose logs app

# Verificar arquivo de erro PHP
tail -100 /var/log/php-errors.log
```

### Testes falhando

```bash
# Limpar cache
rm -rf .phpunit.cache

# Rodar com verbose
vendor/bin/phpunit --verbose
```

## 📖 Documentação Adicional

- [Guia de Segurança](docs/SECURITY_ENV.md)
- [Correções Realizadas](CORRECOES_E_MELHORIAS_FINALIZADAS.md)
- [Deployment](DEPLOY.md)

## 👥 Contribuição

Veja [CONTRIBUTING.md](CONTRIBUTING.md) para guias de contribuição.

### Processo

1. Fork o repositório
2. Crie uma branch (`git checkout -b feature/sua-feature`)
3. Commit suas mudanças (`git commit -am 'Adicionar feature'`)
4. Push para a branch (`git push origin feature/sua-feature`)
5. Abra um Pull Request

### Padrões de Código

- **PSR-12**: Padrão de codificação PHP
- **Type Hints**: Sempre use type hints
- **Testes**: Todo código novo deve ter testes
- **Documentação**: Docstrings para funções públicas

## 📄 Licença

MIT License - veja [LICENSE](LICENSE) para detalhes

## 📞 Suporte

- 📧 Email: suporte@empresa.com
- 🐛 Issues: [GitHub Issues](https://github.com/YOUR_ORG/sistema-cameras/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/YOUR_ORG/sistema-cameras/discussions)

---

**Última atualização**: 2026-06-01  
**Versão**: 1.0.0  
**Mantido por**: Equipe de Desenvolvimento
