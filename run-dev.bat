@echo off
setlocal

cd /d "%~dp0"

echo ============================================================
echo  Chapter Summary Tool - Starting full dev environment
echo ============================================================
echo.
echo   Backend  (Laravel)  : http://127.0.0.1:8000
echo   Frontend (Vite)     : http://127.0.0.1:5173
echo   Reverb (WebSockets) : ws://127.0.0.1:6001
echo   Queue worker        : redis connection
echo.
echo Each service opens in its own window. Close this window LAST,
echo or close each service window individually to stop it.
echo ============================================================
echo.

if not exist node_modules (
    echo [!] node_modules not found. Run "npm install" first.
    pause
    exit /b 1
)

if not exist vendor (
    echo [!] vendor not found. Run "composer install" first.
    pause
    exit /b 1
)

if not exist .env (
    echo [!] .env not found. Copy .env.example to .env and configure it first.
    pause
    exit /b 1
)

:: التأكد من وجود رابط التخزين للملفات المرفوعة
if not exist public\storage (
    echo [i] Storage link missing. Creating it now...
    call php artisan storage:link
)

echo Checking database connection...
php artisan migrate:status >nul 2>&1
if errorlevel 1 (
    echo [!] Could not reach the database with current .env settings.
    echo     Check DB_CONNECTION / DB_HOST / DB_PORT / DB_DATABASE / DB_USERNAME / DB_PASSWORD.
    pause
    exit /b 1
)
echo Database OK.
echo.

:: فحص ما إذا كان خادم Redis يعمل على المنفذ 6379
echo Checking Redis connection (Port 6379)...
powershell -Command "try { $tcp = New-Object System.Net.Sockets.TcpClient('127.0.0.1', 6379); $tcp.Close(); exit 0 } catch { exit 1 }"
if errorlevel 1 (
    echo [!] WARNING: Redis server does not seem to be running on 127.0.0.1:6379!
    echo [!] The Queue Worker will fail if Redis is not running.
    echo [!] Please ensure Redis is running via Docker, WSL, or Windows Service.
    echo.
    pause
) else (
    echo Redis is running OK.
    echo.
)

start "Chapter Summary Tool - Backend (Laravel)" cmd /k "php artisan serve"
timeout /t 2 /nobreak >nul

start "Chapter Summary Tool - Frontend (Vite)" cmd /k "npm run dev"
timeout /t 2 /nobreak >nul

start "Chapter Summary Tool - Reverb (WebSockets)" cmd /k "php artisan reverb:start --debug"
timeout /t 2 /nobreak >nul

:: تم تغيير queue:work إلى queue:listen لكي يقرأ التعديلات البرمجية فوراً أثناء التطوير
start "Chapter Summary Tool - Queue Worker" cmd /k "php artisan queue:listen redis --tries=2 --timeout=600"
timeout /t 2 /nobreak >nul

if not exist storage\logs\laravel.log type nul > storage\logs\laravel.log

start "Chapter Summary Tool - Logs (laravel.log)" powershell -NoExit -Command "Get-Content -Path 'storage\logs\laravel.log' -Wait -Tail 20"

echo.
echo All services launched. Visit http://127.0.0.1:8000 in your browser.
echo.
echo Seeded accounts (password for both: password):
echo   admin@example.com   (role: admin)
echo   student@example.com (role: student)
echo.
pause