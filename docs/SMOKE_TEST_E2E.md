# Smoke Test E2E - Sistema Cameras

Data: ____/____/______  
Ambiente: ____________________  
Executado por: ____________________

## Instrucoes

1. Preencha a coluna `Status` com `PASS`, `FAIL` ou `N/A`.
2. Em `Evidencia`, registre URL, print, mensagem de erro ou trecho de log.
3. Em `Observacoes`, detalhe comportamento inesperado.

## Matriz de Execucao

| ID | Caso | Passos | Resultado Esperado | Status | Evidencia | Observacoes |
|---|---|---|---|---|---|---|
| A01 | Login page | Abrir `?page=login` | Formulario carrega sem erro JS/CSP |  |  |  |
| A02 | Login CSRF | Remover `csrf_token` no DevTools e enviar login | Bloqueio por CSRF (nao autentica) |  |  |  |
| A03 | Login valido admin | Login com admin | Redireciona para `home` |  |  |  |
| A04 | Timeout sessao | Ficar inativo ate expirar | Volta para login |  |  |  |
| A05 | Logout | Clicar em sair | Sessao encerrada; voltar do navegador nao reabre area logada |  |  |  |
| B01 | User sem admin | Logar como `user`; abrir `?page=administracao` | Acesso negado/redirecionado |  |  |  |
| B02 | User sem supervisor | `user` abrir `?page=relatorios_cameras` | Acesso negado/redirecionado |  |  |  |
| B03 | Supervisor permitido | Logar como `supervisor`; abrir `cadastro_cameras` e `relatorios_cameras` | Acesso permitido |  |  |  |
| B04 | Supervisor sem admin | `supervisor` abrir `?page=administracao` | Acesso negado/redirecionado |  |  |  |
| B05 | Admin completo | Logar como `admin` e abrir paginas acima | Acesso permitido |  |  |  |
| C01 | Cadastro novo modelo | Cadastrar camera com novo modelo | Sucesso e aparece na listagem |  |  |  |
| C02 | Cadastro modelo existente | Cadastrar camera com modelo existente | Sucesso |  |  |  |
| C03 | Listagem e filtros | Abrir `listar_cameras`; filtrar por status/local/pesquisa | Tabela atualiza corretamente |  |  |  |
| C04 | Edicao camera | Editar camera existente | Salva alteracoes e reflete na listagem |  |  |  |
| C05 | Exclusao camera | Excluir camera na listagem (admin/supervisor) | Remove registro com sucesso |  |  |  |
| D01 | Listar usuarios | Abrir `?page=listarUsuario` com admin | Lista carrega com data exibida |  |  |  |
| D02 | Criar usuario | Criar usuario em `?page=administracao` | Sucesso |  |  |  |
| D03 | Bloquear/ativar usuario | Bloquear e reativar usuario | Status muda corretamente |  |  |  |
| D04 | Autoexclusao bloqueada | Tentar excluir o proprio usuario | Sistema bloqueia a acao |  |  |  |
| E01 | API session sem login | Chamar `?page=api/session_check` sem sessao | 401 / sessao nao encontrada |  |  |  |
| E02 | Renovar sem CSRF | POST `?page=api/renovar_sessao` sem `X-CSRF-Token` | Rejeitado |  |  |  |
| E03 | API protegida sem login | Chamar `?page=api/api_cameras` sem sessao | Rejeitado |  |  |  |
| E04 | POST protegido sem token | Editar/excluir sem CSRF | Rejeitado |  |  |  |
| F01 | CSP home | Abrir `home` e console | Sem erro CSP |  |  |  |
| F02 | CSP listagem | Abrir `listar_cameras` e usar botoes | Sem erro CSP |  |  |  |
| F03 | CSP edicao | Abrir `editar_cameras` | Sem erro CSP |  |  |  |

## Resumo Final

- Total executado: ______
- PASS: ______
- FAIL: ______
- N/A: ______

Defeitos abertos:

1. ________________________________
2. ________________________________
3. ________________________________

