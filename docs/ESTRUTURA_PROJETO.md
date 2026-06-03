# Estrutura de Pastas do Projeto

## Estrutura atual (resumo)

- `public/`: front controller (`index.php`), assets e imagens.
- `api/`: endpoints HTTP (JSON/SSE).
- `auth/`: login/logout.
- `accounts/`: telas e acoes de administracao de usuarios.
- `inc/`: includes compartilhados (header/footer/navbar, controle de acesso, rotas permitidas).
- `config/`: configuracoes da aplicacao e banco.
- `config/DB/`: scripts SQL.
- `resources/`: paginas do sistema (cadastro, listagem, home etc).

## Pontos de atencao

- Padronizar encoding UTF-8 sem BOM para evitar textos corrompidos.
- Consolidar helpers de API (ex.: `api/api_utils.php` vs `inc/api_bootstrap.php`).
- Remover ou documentar o fallback para `/scripts` no resolver de paginas.
- Mistura de responsabilidades de view e regra em alguns arquivos de pagina.

## Estrutura alvo (incremental, sem quebra)

- `public/`
- `src/`
- `src/Http/Controller/` (paginas e APIs)
- `src/Http/Middleware/` (auth/csrf/permissoes)
- `src/Domain/` (regras de negocio)
- `src/Infra/Database/` (conexao/repositorios)
- `src/View/` (templates)
- `config/`
- `database/` (migrations/seeds)
- `docs/`

## Plano de migracao sugerido

1. Padronizar nomes de pastas e remover fallbacks antigos (ex.: `/scripts`).
2. Centralizar middleware de seguranca (auth + csrf + roles) para APIs e paginas.
3. Extrair consultas SQL de paginas para camada de repositorio/servico.
4. Consolidar JS usado e remover duplicados legados.
5. Padronizar nomes de arquivos e encoding UTF-8 em todo o projeto.
## Ambiente e configuracao

- `config/app.php` define `ENVIRONMENT` a partir de `CAMERAS_ENV` (padrao `development`).
- `config/config.php` exige `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` quando `ENVIRONMENT=production`.

