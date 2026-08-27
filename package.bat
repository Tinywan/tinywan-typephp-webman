@echo off
setlocal enabledelayedexpansion

echo ===================================================
echo   Webman 2.x x TypePHP One-Click Build and Package
echo ===================================================

cd /d "%~dp0"

if "%PHP_HOME%"=="" set "PHP_HOME=D:\workspace\tpc_v0.6.5_windows_x86_64"

rem [Step 0] Release any potential process file locks
taskkill /f /im webman-server.exe >nul 2>nul
taskkill /f /im webman_server.exe >nul 2>nul
taskkill /f /im tpc.exe >nul 2>nul

set "SKIP_BUILD=0"
if "%~1"=="--no-build" set "SKIP_BUILD=1"

if "%SKIP_BUILD%"=="1" (
    echo [1/6] Skipping build step: --no-build specified
    if not exist "build\webman-server.exe" if not exist "build\webman_server.exe" (
        echo [ERROR] Compiled binary not found in build directory! Please run without --no-build.
        exit /b 1
    )
) else (
    echo [1/6] Compiling project binary with build_env.bat ...
    call "%~dp0build_env.bat"
    if errorlevel 1 (
        echo [ERROR] Compilation failed! Aborting packaging.
        exit /b 1
    )
    if not exist "build\webman-server.exe" if not exist "build\webman_server.exe" (
        echo [ERROR] Executable was not generated in build directory!
        exit /b 1
    )
)

set "DIST_DIR=%~dp0dist"
echo [2/6] Creating dist directory: %DIST_DIR%
if exist "%DIST_DIR%" rd /s /q "%DIST_DIR%" 2>nul
mkdir "%DIST_DIR%" 2>nul
mkdir "%DIST_DIR%\ext" 2>nul
mkdir "%DIST_DIR%\public" 2>nul
mkdir "%DIST_DIR%\runtime" 2>nul
mkdir "%DIST_DIR%\runtime\logs" 2>nul
mkdir "%DIST_DIR%\runtime\views" 2>nul

echo [3/6] Copying compiled executable...
if exist "build\webman_server.exe" copy /y "build\webman_server.exe" "%DIST_DIR%\webman-server.exe" >nul
if exist "build\webman-server.exe" copy /y "build\webman-server.exe" "%DIST_DIR%\webman-server.exe" >nul

echo [4/6] Copying runtime DLLs and extensions from SDK...
copy /y "%PHP_HOME%\*.dll" "%DIST_DIR%\" >nul
copy /y "%PHP_HOME%\ext\*.dll" "%DIST_DIR%\ext\" >nul

echo [5/6] Generating portable php.ini...
(
echo [PHP]
echo engine = On
echo short_open_tag = Off
echo precision = 14
echo output_buffering = 4096
echo zlib.output_compression = Off
echo implicit_flush = Off
echo serialize_precision = -1
echo zend.enable_gc = On
echo zend.exception_ignore_args = On
echo zend.exception_string_param_max_len = 0
echo max_execution_time = 0
echo max_input_time = 60
echo memory_limit = 512M
echo error_reporting = E_ALL ^& ~E_DEPRECATED ^& ~E_STRICT
echo display_errors = On
echo display_startup_errors = On
echo log_errors = On
echo report_memleaks = On
echo default_mimetype = "text/html"
echo default_charset = "UTF-8"
echo extension_dir = "ext"
echo extension=curl
echo extension=mbstring
echo extension=openssl
echo extension=pdo_mysql
echo extension=pdo_sqlite
echo extension=sockets
echo extension=fileinfo
echo.
echo [Date]
echo date.timezone = Asia/Shanghai
echo.
echo [opcache]
echo opcache.enable = 0
echo opcache.enable_cli = 0
) > "%DIST_DIR%\php.ini"

echo [6/6] Copying dynamic runtime files and configs...
if exist "config" (
    mkdir "%DIST_DIR%\config" 2>nul
    xcopy /s /e /y "config\*" "%DIST_DIR%\config\" >nul
)
if exist "public" (
    mkdir "%DIST_DIR%\public" 2>nul
    xcopy /s /e /y "public\*" "%DIST_DIR%\public\" >nul
)
if exist "app\view" (
    mkdir "%DIST_DIR%\app\view" 2>nul
    xcopy /s /e /y "app\view\*" "%DIST_DIR%\app\view\" >nul
)
if exist ".env" copy /y ".env" "%DIST_DIR%\" >nul

(
echo @echo off
echo setlocal
echo set "PHPRC=%%~dp0"
echo set "PATH=%%~dp0;%%PATH%%"
echo cd /d "%%~dp0"
echo echo Starting Webman Server on http://127.0.0.1:8787 ...
echo "%%~dp0webman-server.exe"
) > "%DIST_DIR%\start_server.bat"

(
echo @echo off
echo setlocal
echo set "PHPRC=%%~dp0"
echo set "PATH=%%~dp0;%%PATH%%"
echo cd /d "%%~dp0"
echo "%%~dp0webman-server.exe" %%*
) > "%DIST_DIR%\run.bat"

echo.
echo ===================================================
echo [SUCCESS] Standalone Webman package ready at: %DIST_DIR%
echo - Run Server: dist\start_server.bat
echo - Run CLI:    dist\run.bat
echo ===================================================
