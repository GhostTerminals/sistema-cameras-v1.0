@echo off
title Configurador XAMPP - Sistema de Cameras v1.0
chcp 65001 >nul

echo ===============================================================================
echo  Configurador XAMPP - Sistema de Cameras e Alarmes v1.0
echo ===============================================================================
echo.
echo  Este script configura o projeto para rodar no XAMPP no Windows.
echo.
echo  Requisitos:
echo    - XAMPP com PHP 8.1+ instalado em C:\xampp
echo    - MySQL rodando (painel XAMPP)
echo    - Apache com mod_rewrite habilitado
echo.

:: Verificar se está em C:\htdocs
set "PROJECT_DIR=%CD%"
for %%I in ("%PROJECT_DIR%") do set "FOLDER_NAME=%%~nxI"

echo  Projeto detectado em: %PROJECT_DIR%
echo  Acessar via: http://localhost/%FOLDER_NAME%/public/
echo.

:: ── 1. Configurar .env ─────────────────────────────────────────────────────
echo  [1/4] Verificando .env...

if exist ".env" (
    echo    .env ja existe.
) else (
    echo    Criando .env a partir de .env.example...
    copy .env.example .env >nul
    echo    ATENCAO: Edite .env com as credenciais do seu MySQL (DB_HOST=localhost).
)

:: ── 2. Verificar extensoes PHP ─────────────────────────────────────────────
echo  [2/4] Verificando extensoes PHP...

php -m 2>nul | findstr /i "pdo" >nul
if %errorlevel% equ 0 ( echo    [OK] PDO ) else ( echo    [FALTA] PDO - habilite em php.ini: extension=php_pdo_mysql.dll )

php -m 2>nul | findstr /i "pdo_mysql" >nul
if %errorlevel% equ 0 ( echo    [OK] pdo_mysql ) else ( echo    [FALTA] pdo_mysql - habilite em php.ini )

php -m 2>nul | findstr /i "mysqli" >nul
if %errorlevel% equ 0 ( echo    [OK] mysqli ) else ( echo    [FALTA] mysqli - habilite em php.ini )

php -m 2>nul | findstr /i "mbstring" >nul
if %errorlevel% equ 0 ( echo    [OK] mbstring ) else ( echo    [FALTA] mbstring - habilite em php.ini )

php -m 2>nul | findstr /i "openssl" >nul
if %errorlevel% equ 0 ( echo    [OK] openssl ) else ( echo    [FALTA] openssl - habilite em php.ini )

php -m 2>nul | findstr /i "gd" >nul
if %errorlevel% equ 0 ( echo    [OK] GD ) else ( echo    [AVISO] GD nao habilitado - algumas funcionalidades de imagem podem falhar )

:: ── 3. Criar banco de dados ────────────────────────────────────────────────
echo  [3/4] Configurando banco de dados...

set "MYSQL_PATH=C:\xampp\mysql\bin"
if exist "%MYSQL_PATH%\mysql.exe" (
    echo    MySQL encontrado em %MYSQL_PATH%
    echo    Criando banco cftv_gml e importando schema...

    :: Ler credenciais do .env
    setlocal enabledelayedexpansion
    for /f "tokens=1,* delims==" %%a in (.env) do (
        if "%%a"=="DB_USER" set "DB_USER=%%b"
        if "%%a"=="DB_PASS" set "DB_PASS=%%b"
        if "%%a"=="DB_NAME" set "DB_NAME=%%b"
    )

    "%MYSQL_PATH%\mysql" -u %DB_USER% -p%DB_PASS% < "config\DB\cftv_gml.sql"
    if %errorlevel% equ 0 (
        echo    [OK] Banco criado e populado com sucesso!
    ) else (
        echo    [AVISO] Falha ao importar SQL. Verifique credenciais em .env e se o MySQL esta rodando.
        echo    Comando manual: "%MYSQL_PATH%\mysql" -u SEU_USUARIO -p < "config\DB\cftv_gml.sql"
    )
    endlocal
) else (
    echo    [AVISO] MySQL CLI nao encontrado em %MYSQL_PATH%
    echo    Importe manualmente: mysql -u SEU_USUARIO -p < config\DB\cftv_gml.sql
)

:: ── 4. Verificar mod_rewrite ───────────────────────────────────────────────
echo  [4/4] Verificando Apache mod_rewrite...

if exist "C:\xampp\apache\conf\httpd.conf" (
    findstr /i "mod_rewrite" "C:\xampp\apache\conf\httpd.conf" | findstr /v "^#" >nul
    if !errorlevel! equ 0 (
        echo    [OK] mod_rewrite parece habilitado.
    ) else (
        echo    [AVISO] mod_rewrite pode estar desabilitado.
        echo    Descomente esta linha em C:\xampp\apache\conf\httpd.conf:
        echo      LoadModule rewrite_module modules/mod_rewrite.so
    )
)

echo.
echo ===============================================================================
echo  Configuracao concluida!
echo.
echo  Proximo passo:
echo    1. Inicie Apache e MySQL no XAMPP Control Panel
echo    2. Abra no navegador: http://localhost/%FOLDER_NAME%/public/
echo    3. Credenciais padrao (se aplicavel): admin / admin
echo.
echo  Resumo de URLs:
echo    - Projeto:  http://localhost/%FOLDER_NAME%/public/
echo    - API:      http://localhost/%FOLDER_NAME%/public/index.php?page=api/api_ping
echo.
echo  IMPORTANTE: Para trocar entre XAMPP e Docker, basta alterar DB_HOST no .env:
echo    - XAMPP: DB_HOST=localhost
echo    - Docker: DB_HOST=db
echo ===============================================================================
pause
