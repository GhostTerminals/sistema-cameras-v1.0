# Testes Iniciais (PR-04)

Este diretório contém smoke tests básicos de segurança/sessão.

## Executar

```powershell
powershell -ExecutionPolicy Bypass -File .\tests\smoke_security.ps1 -BaseUrl "http://localhost/sistema-cameras-v1.0/public"
```

## Cobertura inicial

- `api_health` sem autenticação deve retornar `401`.
- Endpoint protegido sem sessão deve retornar `401`.
- Método HTTP inválido deve retornar `405`.

