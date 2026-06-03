# Checklist de Producao

## Ambiente
1. Definir `CAMERAS_ENV=production`.
2. Definir `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Definir `CAMERAS_SESSION_TIMEOUT` e `CAMERAS_SESSION_ABSOLUTE_TIMEOUT`.
4. Garantir HTTPS ativo (cookies `secure`).
5. Ajustar `CAMERAS_CSP_ALLOW_INLINE_STYLES`:
   - `1` se Bootstrap/Tooltips precisarem de inline style.
   - `0` se tudo estiver compatível sem inline style.

## Migracoes
1. Executar `config/DB/cftv_gml.sql` (schema base com tabelas de auditoria, sessao unica e tentativas de login).
2. Confirmar no banco as tabelas: `auditoria_eventos`, `login_attempts`, `user_sessions`.
3. Em ambiente legado (ou quando existir schema antigo de `login_attempts`), executar `config/DB/migrations/003_standardize_login_attempts.sql`.

## Fluxos Criticos
1. Login valido.
2. Login invalido (validar rate-limit).
3. Troca de senha obrigatoria.
4. Logout normal.
5. Logout global (menu do usuario).
6. CRUD cameras:
   - Cadastrar, editar, excluir.
7. CRUD alarmes:
   - Cadastrar, editar.
8. Relatorios:
   - `relatorios_cameras` e `relatorios_alarmes`.
9. Sessao:
   - Expirar por inatividade.
   - Expirar por tempo maximo.

## Verificacoes Visuais (CSP)
1. Tooltips e modais funcionam.
2. Loading overlays aparecem.
3. Paginacao funciona.

## Healthcheck
1. Acessar `index.php?page=api/api_health` e validar:
   - `db_connection = true`
   - `table_auditoria_eventos = true`
   - `table_login_attempts = true`
   - `table_user_sessions = true`
   - `https = true`

## Observacoes
- Se o CSP bloquear estilos dinamicos do Bootstrap, manter `CAMERAS_CSP_ALLOW_INLINE_STYLES=1`.
