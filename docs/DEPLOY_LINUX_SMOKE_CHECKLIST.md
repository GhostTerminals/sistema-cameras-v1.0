# Deploy Linux - Smoke Checklist

## 1) Pre-requisitos do servidor

- PHP 8.1+ com extensoes: `pdo`, `pdo_mysql`, `mbstring`, `json`, `session`, `openssl`.
- MySQL 8+ com `utf8mb4`.
- Servidor web:
  - Apache com `mod_rewrite`, ou
  - Nginx com `try_files`.

Comandos uteis:

```bash
php -v
php -m | egrep "pdo|pdo_mysql|mbstring|json|session|openssl"
mysql --version
```

## 2) Estrutura e permissao de arquivos

- Public root deve apontar para `public/` (nao para raiz do projeto).
- Usuario do webserver precisa ler todo projeto.
- Se houver logs/cache/upload, conceder escrita apenas nessas pastas.

Exemplo (ajuste usuario/grupo):

```bash
sudo chown -R www-data:www-data /var/www/sistema-cameras-v1.0
sudo find /var/www/sistema-cameras-v1.0 -type d -exec chmod 755 {} \;
sudo find /var/www/sistema-cameras-v1.0 -type f -exec chmod 644 {} \;
```

## 3) Variaveis de ambiente obrigatorias

Definir no vhost/systemd/infra:

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `APP_TIMEZONE` (ex.: `America/Sao_Paulo`)

Validar no runtime:

```bash
php -r "echo getenv('DB_HOST'), PHP_EOL, getenv('DB_NAME'), PHP_EOL;"
```

## 4) Banco de dados

Executar script principal:

```bash
mysql -u root -p < config/DB/cftv_gml.sql
```

Se for atualizacao de ambiente legado (com estrutura antiga de `login_attempts`), executar tambem:

```bash
mysql -u root -p cftv_gml < config/DB/migrations/003_standardize_login_attempts.sql
```

Sanidade minima:

```sql
USE cftv_gml;
SHOW TABLES;
SELECT COUNT(*) AS usuarios FROM usuarios;
SELECT COUNT(*) AS status FROM status;
SELECT COUNT(*) AS modelos FROM catalogo_modelos;
```

## 5) Configuracao web (essencial)

### Apache

- `DocumentRoot` para `/var/www/sistema-cameras-v1.0/public`
- `AllowOverride All` (se usar `.htaccess`)
- `mod_rewrite` habilitado

### Nginx

- Root para `/var/www/sistema-cameras-v1.0/public`
- `index index.php;`
- Fallback para `index.php` (front controller)

## 6) Smoke test HTTP (apos subir)

Substitua `https://seu-dominio`.

```bash
curl -I https://seu-dominio/
curl -I "https://seu-dominio/index.php?page=login"
curl -i "https://seu-dominio/index.php?page=api/api_status"
```

Esperado:

- `/` e `?page=login` respondem `200`.
- `api_status` sem sessao responde `401` JSON (comportamento correto).

## 7) Smoke test funcional (navegador)

1. Abrir login.
2. Autenticar com usuario valido.
3. Abrir:
   - Dashboard
   - Cadastro de cameras
   - Listagem de cameras
   - Relatorios
4. Cadastrar camera de teste.
5. Editar camera de teste.
6. Excluir camera (perfil permitido).

## 8) Validacoes de seguranca rapidas

- Endpoint sensivel sem sessao retorna `401`.
- Acao sem permissao retorna `403`.
- Requisicao POST sem CSRF retorna `403`.
- Sessao expirada redireciona para login.

## 9) Encoding e locale

Ja configurado no projeto:

- `UTF-8` global em `config/config.php`
- `utf8mb4` na conexao MySQL
- `Content-Type` com `charset=UTF-8`
- Locale `pt-BR`

Confirmar rapidamente:

```bash
curl -I https://seu-dominio/ | egrep -i "content-type|content-language"
```

## 10) Checklist final de go-live

1. Backup do banco anterior (se migracao).
2. Deploy de codigo.
3. Aplicar variaveis de ambiente.
4. Rodar script SQL.
5. Reiniciar PHP-FPM/Apache/Nginx.
6. Rodar smoke test HTTP.
7. Rodar smoke test funcional.
8. Monitorar logs por 15-30 min.
Observacao importante:
- Em producao (`CAMERAS_ENV=production`), o sistema exige que `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS` estejam definidos.
- Em desenvolvimento (padrao), se essas variaveis nao existirem, o sistema usa os fallbacks de `config/config.php`.

