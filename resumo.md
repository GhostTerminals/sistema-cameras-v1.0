Resumo
Analisei a estrutura do projeto e rodei o que foi possível. É um sistema PHP 8.1+ com API v2, PDO, CSRF, sessão única, Docker, PHPUnit e Vitest. A base já tem boas práticas, mas há correções importantes antes de confiar em produção.
Correções Críticas
Corrigir o healthcheck do Docker
O healthcheck chama api/api_health sem autenticação em Dockerfile (line 50) e docker-compose.yml (line 26), mas o endpoint exige admin em api_health.php (line 9).
Sugestão: criar um endpoint público mínimo para healthcheck, sem dados sensíveis, ou ajustar o healthcheck para algo realmente público.

Adicionar autorização por perfil nas APIs
As páginas têm regras em access_rules.php (line 3), mas APIs de cadastro/edição/exclusão validam só sessão, por exemplo api_cadastrar_cameras.php (line 13) e api_excluir_camera.php (line 13).
Risco: usuário logado comum pode chamar endpoints diretamente e contornar restrições da interface.

Reforçar segurança de anexos/uploads
Uploads são salvos em public/uploads (line 95) e retornados como URL pública em api_listar_anexos.php (line 91). Há .htaccess, mas isso depende de Apache.
Sugestão: mover arquivos para storage privado e servir via endpoint autenticado, ou garantir bloqueio equivalente em Nginx/Docker.

Proteger exclusão de arquivos contra path traversal via banco
A exclusão monta caminho com valor vindo do banco antes de unlink em api_excluir_anexo.php (line 38).
Sugestão: resolver caminho real com realpath, validar que está dentro do diretório permitido e só então excluir.

Corrigir chamada de auditoria em exclusão de anexo
auditEvent (line 174) espera banco, entidade, id, operação etc., mas api_excluir_anexo.php (line 62) chama com assinatura diferente.
Resultado provável: auditoria falha silenciosamente.

Testes/CI
6. Corrigir teste JS quebrado
npm.cmd test rodou e falhou: window is not defined e showToast não está exportado/global no teste. O problema está em showToast.test.js (line 5) e main.js (line 247).
Sugestão: configurar Vitest com jsdom ou testar módulo exportado de forma explícita.
Instalar/validar dependências PHP no ambiente
Não há pasta vendor/ e composer não está no PATH. Não consegui rodar PHPUnit.
Sugestão: garantir composer install local/CI e documentar setup Windows.

Remover || true do CI onde deve falhar
PHPStan e smoke test podem falhar sem quebrar pipeline em .github/workflows/ci.yml (line 23) e .github/workflows/ci.yml (line 111).
Isso mascara regressões.

Melhorias Importantes
9. Revisar recuperação de senha
recuperar_senha.php (line 26) gera nova senha temporária apenas com nome de usuário. Mesmo com CSRF, falta confirmação adicional ou fluxo administrativo.
Sugestão: limitar por rate limit, exigir admin, ou usar token de recuperação com expiração.
Padronizar política de senha
A política central exige mínimo 8 em security.php (line 14), mas schema de login aceita mínimo 6 em ValidationSchema.php (line 172).
Sugestão: usar uma única fonte de verdade.

Ignorar uploads reais no Git
.gitignore só ignora public/uploads/*.tmp e public/uploads/temp/ em .gitignore (line 24), mas há vários anexos reais em public/uploads.
Sugestão: versionar só .gitkeep/.htaccess e ignorar conteúdo gerado.

Melhorar database::insert
A tabela tem whitelist, mas nomes de colunas vêm direto de array_keys($data) em database.php (line 153).
Sugestão: validar colunas permitidas por tabela.

Verificação executada
npm.cmd test passou com 1 arquivo e 2 testes JS. O teste showToast foi ajustado para executar em ambiente Node/Vitest com um DOM mínimo, e main.js agora expõe showToast/safeToast no window de forma explícita.

Composer foi corrigido no PATH, vendor/ foi instalado com composer install e a extensão zip foi habilitada no PHP CLI do XAMPP. PHPUnit rodou com PHP 8.2.12 e passou: 24 testes, 65 assertions, 3 skipped. Os 3 skipped são os testes de API quando não há servidor acessível em TEST_BASE_URL ou http://localhost:8080.

composer validate passou, restando apenas o aviso de licença ausente no composer.json. git ainda não está no PATH, então não consegui confirmar por git se .env ou uploads estão versionados.

Continua��o em 03/06/2026
Git agora est� no PATH: git version 2.54.0.windows.1. Mesmo assim, a pasta C:\xampp\htdocs\sistema-cameras-v1.0 ainda n�o � um reposit�rio Git: git status e git rev-parse --show-toplevel retornam "not a git repository". Portanto ainda n�o foi poss�vel confirmar por status do Git quais arquivos est�o versionados.

npm.cmd test passou novamente: 1 arquivo de teste, 2 testes, 2 passed.

composer validate passou novamente, mantendo apenas o aviso de licen�a ausente no composer.json.

vendor\bin\phpunit.bat --do-not-cache-result falhou no ambiente atual porque o PHP CLI tentou abrir sess�o em C:\xampp\tmp e recebeu Permission denied. Reexecutando com session.save_path apontando para .tmp\sessions dentro do projeto, o PHPUnit passou: 24 testes, 65 assertions, 3 skipped. Os 3 skipped continuam sendo os testes de API sem servidor acess�vel.

.gitignore foi atualizado para ignorar .tmp/, criada apenas como diret�rio local de sess�o para a execu��o do PHPUnit.
