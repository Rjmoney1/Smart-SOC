@echo off
REM ─────────────────────────────────────────────────────────────────────────────
REM  CyberAI Platform — Quick Start Script (Windows)
REM ─────────────────────────────────────────────────────────────────────────────
echo.
echo  ============================================================
echo   CyberAI Platform ^| Docker Startup
echo  ============================================================
echo.

REM Check Docker is running
docker info >nul 2>&1
IF %ERRORLEVEL% NEQ 0 (
    echo  [ERROR] Docker is not running! Please start Docker Desktop.
    pause
    exit /b 1
)

echo  [1/3] Stopping any existing containers...
docker compose -f docker/docker-compose.yml down --remove-orphans 2>nul

echo.
echo  [2/3] Building images...
docker compose -f docker/docker-compose.yml build --parallel

echo.
echo  [3/3] Starting all services...
docker compose -f docker/docker-compose.yml up -d

echo.
echo  ============================================================
echo   Services starting up — please wait ~30 seconds for MySQL
echo  ============================================================
echo.
echo   Dashboard:   http://localhost
echo   phpMyAdmin:  Run with --profile dev flag
echo.
echo   Default login:
echo     Username: admin
echo     Password: Admin@123
echo.
echo  To view logs:   docker compose -f docker/docker-compose.yml logs -f
echo  To stop:        docker compose -f docker/docker-compose.yml down
echo.
pause
