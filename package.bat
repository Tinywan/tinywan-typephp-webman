@echo off
setlocal

rem 1. Run build
echo [INFO] Step 1: Running build.bat ...
call "%~dp0build.bat"
if %ERRORLEVEL% neq 0 (
    echo [ERROR] Build failed with error code %ERRORLEVEL%. Packaging aborted.
    exit /b %ERRORLEVEL%
)

rem 2. Sync to dist directory
echo [INFO] Step 2: Packaging artifacts and runtime resources into dist ...

if not defined PHP_HOME set "PHP_HOME=D:\workspace\tpc_v0.6.5_windows_x86_64"
if "%PHP_HOME:~-1%"=="\" set "PHP_HOME=%PHP_HOME:~0,-1%"

if not exist "%~dp0dist" mkdir "%~dp0dist"

rem Copy executable
if exist "%~dp0build\webman-server.exe" (
    copy /y "%~dp0build\webman-server.exe" "%~dp0dist\webman-server.exe" >nul
) else if exist "%~dp0build\webman_server.exe" (
    copy /y "%~dp0build\webman_server.exe" "%~dp0dist\webman-server.exe" >nul
)

rem Copy DLL and extensions
if exist "%PHP_HOME%\*.dll" copy /y "%PHP_HOME%\*.dll" "%~dp0dist\" >nul
if not exist "%PHPX_HOME%\build\phpx.dll" (
    echo [ERROR] Required PHPX runtime DLL not found: %PHPX_HOME%\build\phpx.dll
    exit /b 1
)
copy /y "%PHPX_HOME%\build\phpx.dll" "%~dp0dist\" >nul
rem phpx 2.7.0 stages the mpdecimal runtime DLLs next to phpx.dll in build\, not lib\.
for %%F in (libmpdec-4.0.1.dll libmpdec++-4.0.1.dll) do (
    if exist "%PHPX_HOME%\build\%%F" (
        copy /y "%PHPX_HOME%\build\%%F" "%~dp0dist\" >nul
    ) else if exist "%PHPX_HOME%\lib\%%F" (
        copy /y "%PHPX_HOME%\lib\%%F" "%~dp0dist\" >nul
    ) else (
        echo [ERROR] Required mpdecimal runtime DLL not found: %PHPX_HOME%\build\%%F
        exit /b 1
    )
)
rem GMP/MPFR are installed by the Windows CI job through vcpkg. Keep their
rem runtime DLLs beside webman-server when the dynamic x64-windows triplet is used.
if defined GMP_MPFR_ROOT if exist "%GMP_MPFR_ROOT%\bin\*.dll" copy /y "%GMP_MPFR_ROOT%\bin\*.dll" "%~dp0dist\" >nul
if exist "%PHP_HOME%\ext" xcopy /y /e /i /q "%PHP_HOME%\ext" "%~dp0dist\ext" >nul

rem Copy config and static assets
if exist "%~dp0config" xcopy /y /e /i /q "%~dp0config" "%~dp0dist\config" >nul
if exist "%~dp0public" xcopy /y /e /i /q "%~dp0public" "%~dp0dist\public" >nul
if exist "%~dp0app\view" xcopy /y /e /i /q "%~dp0app\view" "%~dp0dist\app\view" >nul
if exist "%~dp0php.ini" copy /y "%~dp0php.ini" "%~dp0dist\php.ini" >nul

rem Generate run.bat for dist
echo @echo off > "%~dp0dist\run.bat"
echo setlocal >> "%~dp0dist\run.bat"
echo set "PHPRC=%%~dp0" >> "%~dp0dist\run.bat"
echo set "PATH=%%~dp0;%%PATH%%" >> "%~dp0dist\run.bat"
echo cd /d "%%~dp0" >> "%~dp0dist\run.bat"
echo "%%~dp0webman-server.exe" %%* >> "%~dp0dist\run.bat"

echo [INFO] Packaging completed successfully! Dist path: %~dp0dist
