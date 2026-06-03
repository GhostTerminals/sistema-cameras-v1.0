# Sistema de Gerenciamento de Câmeras e Alarmes

Sistema web para gestão de equipamentos de monitoramento (câmeras, DVRs, LPRs, totens) e alarmes.

## Stack

- **Backend:** PHP 8.1+ (puro, sem framework)
- **Banco:** MySQL 8.0 / MariaDB 10.6+
- **Frontend:** Bootstrap 5.3, jQuery 3.7, DataTables 2.x
- **Infra:** Docker + Docker Compose

## Estrutura

```
├── api/                   # Endpoints REST
├── accounts/              # Gestão de usuários
├── auth/                  # Login/logout
├── config/                # Configurações + schema SQL
│   └── DB/cftv_gml.sql    # Schema completo
├── inc/                   # Componentes PHP reutilizáveis
├── public/                # Document root
│   ├── assets/js/         # JS específicos por página
│   ├── assets/css/        # Estilos
│   └── index.php          # Front controller
├── resources/             # Views (páginas)
├── scripts/               # Backup, restore, monitoramento
├── src/                   # Classes PHP (PSR-4 App\)
│   ├── ErrorHandler.php   # Tratamento global de erros
│   └── Exceptions.php     # Exceções customizadas
└── tests/                 # Testes automatizados
```

## Instalação rápida (Docker)

```bash
docker compose up -d
```

Acesse `http://localhost:8080`.

Sem Docker: aponte o document root para `public/`, configure `.env` com dados do banco e importe `config/DB/cftv_gml.sql`.

## Testes

```bash
# PHP (requer phpunit 10+)
composer test

# JavaScript (requer Node.js)
npm test

# Smoke (PowerShell)
pwsh tests/smoke_manutencao.ps1
```

## Backup

```bash
# Linux
DB_PASS="sua_senha" bash scripts/backup.sh

# Windows
.\scripts\backup.ps1 -DbPass "sua_senha"
```

## CI/CD

O pipeline GitHub Actions em `.github/workflows/ci.yml` executa:
- Syntax check em todos os PHP
- Testes PHPUnit com MySQL dedicado
- Smoke tests de segurança

## Licença

Uso interno GML.
