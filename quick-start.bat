@echo off
echo Starting Flashit MilkshakePub Docker Stack...
echo.

REM Copy env file if it doesn't exist
if not exist .env (
    echo Creating .env from template...
    copy .env.example .env
)

REM Start Docker Compose
echo Starting containers...
docker compose up -d --build

if %errorlevel% neq 0 (
    echo.
    echo ERROR: Failed to start containers. Make sure Docker Desktop is running.
    pause
    exit /b 1
)

echo.
echo Waiting for services to be ready...
timeout /t 5 /nobreak >nul

echo.
echo =====================================
echo Flashit MilkshakePub is running!
echo =====================================
echo.
echo App URL: http://localhost:8080
echo.
echo Login credentials:
echo   Check your .env file for ADMIN_PASS
echo.
echo To stop: docker compose down
echo To view logs: docker compose logs -f
echo =====================================
echo.

REM Open browser
start http://localhost:8080

echo Browser opened. Press any key to exit...
pause >nul