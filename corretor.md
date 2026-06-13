Análise Completa do Projeto - Correções e Melhorias
CORREÇÕES CRÍTICAS
1. Senha real no .env.example (commitado no git)
Arquivo: .env.example:26
A senha ykB]Af4|2kHPU#;1g+?J3z)P]=p|AX=R parece ser uma senha real, não placeholder. Está visível publicamente no repositório. Substituir por CHANGE_ME_strong_password_here.
2. EquipamentoService omite colunas da migration 003
Arquivo: src/Services/EquipamentoService.php:140,166
- createLocation() não inclui regiao_id nem horario_funcionamento no INSERT
- updateLocationFields() não inclui regiao_id nem horario_funcionamento no UPDATE
- Impacto: Câmeras criadas via API perdem dados de região/horário do local
3. Dados duplicados em auditoria (triggers + aplicação)
Arquivo: Triggers AFTER INSERT/UPDATE/BEFORE DELETE na tabela equipamentos + api_cadastrar_cameras.php:48, api_editar_camera.php:70, api_excluir_camera.php:51
- Cada operação de equipamento gera 2 registros de auditoria (trigger + auditEvent() manual)
- Impacto: Desperdício de storage e confusão na leitura do log
4. Swagger com CORS aberto e sem autenticação
Arquivo: api/v2/swagger.php:17
header('Access-Control-Allow-Origin: *') bypassa todas as restrições CORS e não requer autenticação, expondo a spec completa da API
5. XSS em ui-utils.js:93
Arquivo: public/assets/js/utils/ui-utils.js:93
bodyEl.innerHTML = htmlContent recebe HTML cru como parâmetro - se qualquer chamador passa conteúdo do usuário, é XSS direto
CORREÇÕES ALTAS
6. api_cameras.php não filtra soft-delete
Arquivo: api/v2/api_cameras.php:48
O whereParts não inclui e.deleted_at IS NULL, fazendo câmeras excluídas aparecerem na listagem. api_dashboard.php filtra corretamente.
7. api_relatorios_cameras.php também sem filtro de soft-delete
Arquivo: api/v2/api_relatorios_cameras.php:87
Mesmo problema - relatórios incluem equipamentos excluídos
8. cadastro_cameras.js crash ao não encontrar form
Arquivo: public/assets/js/cadastro_cameras.js:11
this.tipoSelect = this.form.querySelector(...) executa ANTES do null check em init():18. Se formCadastroCamera não existe => TypeError
9. cadastro_cameras.js - submitToAPI não checa response.ok
Arquivo: public/assets/js/cadastro_cameras.js:681
await response.json() sem verificar response.ok - resposta não-JSON causa SyntaxError não tratado
10. Race condition ao carregar modelos por marca
Arquivo: public/assets/js/cadastro_cameras.js:232
Trocas rápidas de marca disparam fetches concorrentes sem cancelamento. Respostas fora de ordem podem popular dropdown com modelos da marca errada. Usar AbortController.
11. Access rules faltando para páginas de locais
Arquivo: inc/access_rules.php
listar_locais, cadastro_locais, editar_locais, listar_escolas, listar_cmeis, listar_operadoras, listar_proprios não têm restrição de acesso (qualquer user logado acessa). Deveriam ser pelo menos supervisor.
CORREÇÕES MÉDIAS
12. showToast duplicado
- main.js:1-36 tem implementação própria
- ui-utils.js:18-43 tem outra implementação (atribuída a window.showToast)
- O que carrega por último sobrescreve a outra. Consolidar em ui-utils.js apenas.
13. uppercase.js acumula listeners duplicados
Arquivo: public/assets/js/utils/uppercase.js:14
Cada chamada de aplicarUppercaseUniversal() adiciona NOVOS listeners input sem verificar se já existem. Após toggle de modelo 2x, cada campo tem 3 listeners.
14. listar_cameras.js - DOMContentLoaded pode nunca disparar
Arquivo: public/assets/js/listar_cameras.js:399
O IIFE executa imediatamente e registra DOMContentLoaded. Se o script carrega com defer ou no final do body, o evento já disparou e a inicialização falha silenciosamente.
15. putenv() sem validação
Arquivo: config/config.php:3-47
Valores do .env são carregados diretamente sem sanitização. Valores maliciosos poderiam afetar lógica downstream.
16. API relatórios sem cast de tipos
Arquivo: api/v2/api_relatorios_cameras.php:51-56
$_GET['status'], $_GET['local'], $_GET['regiao'] não são convertidos para (int) antes de ir para query parametrizada
17. Migrations não-idempotentes
- 001_fulltext_indexes.sql - ALTER TABLE ADD INDEX falha na segunda execução (nomes duplicados + colisão com schema base)
- 003_locais_regiao_horario.sql - ADD COLUMN falha na segunda execução
- Sugestão: usar IF NOT EXISTS (MariaDB) ou wrapping condicional
18. central_alarmes com status/região em texto livre
- status VARCHAR(20) e regiao VARCHAR(50) sem FK para as tabelas status e regioes
- Inconsistente com equipamentos que usa status_id/regiao_id
19. setTimeout(500) fixo em preencherFormulario
Arquivo: public/assets/js/cadastro_cameras.js:793
Espera 500ms fixo para setar valor do select de modelos. Deveria aguardar a Promise de carregarModelosExistentes
20. Toast tipo 'error' não existe
Arquivo: public/assets/js/cadastro_cameras.js:668
showToast('...', 'error') - os mapas só suportam success, danger, warning, info. Cai para info, ícone azul para erro crítico
21. fetchWithTimeout.js - fallback descarta sinal do chamador
Arquivo: public/assets/js/utils/fetchWithTimeout.js:22
Em browsers sem AbortSignal.any, controller.signal ignora o init.signal do chamador
MELHORIAS SUGERIDAS
Segurança
22. Senha no .env.example → trocar por placeholder
23. swagger.php → removedor CORS wildcard, requerer autenticação
24. Headers de segurança na API bootstrap: faltam X-Frame-Options, HSTS, CSP
25. Password policy → exigir caracteres especiais e maiúsculas (atual aceita password1)
26. IP spoofing em single_session.php → considerar pegar primeiro IP não-confiável da cadeia X-Forwarded-For em vez do último
Código
27. Consolidar showToast → manter apenas em ui-utils.js, remover de main.js
28. Adicionar AbortController nas chamadas fetch de modelo e listagem
29. Verificar response.ok em todos os fetch antes de .json()
30. Adicionar guard no cadastro_cameras.js constructor para form null
31. aplicarUppercaseUniversal → adicionar flag/dataset no campo para evitar listeners duplicados
32. Remover console.error/warn com emojis do código de produção
Banco de Dados
33. Adicionar FK em equipamentos_anexos.manutencao_camera_id e manutencao_alarme_id (têm índice mas sem constraint)
34. Adicionar UNIQUE constraint em locais(nome, secretaria_id) para prevenir duplicatas em race conditions
35. Tornar migrations idempotentes com IF NOT EXISTS ou verificando information_schema
36. Remover triggers de auditoria em equipamentos (quem audita é a aplicação) ou remover as chamadas manuais de auditEvent() para equipamentos
37. Normalizar central_alarmes → converter status e regiao para FK IDs
Arquitetura
38. regiao_id duplicado em locais e equipamentos → definir qual é autoritativo, documentar
39. Padronizar tratamento de erro nos fetch JS → criar helper handleApiError(response) reutilizável
40. Adicionar access_rules para todas as páginas de locais (supervisor mínimo)