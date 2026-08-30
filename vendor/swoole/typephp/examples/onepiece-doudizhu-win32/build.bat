@echo off
rem ============================================================
rem  Build script for the One Piece Dou Dizhu Win32 example.
rem
rem  Run this from the "x64 Native Tools Command Prompt for VS 2022"
rem  (the MSVC environment must be active so that cl.exe, INCLUDE
rem  and LIB are all set up). Usage:
rem
rem      build.bat
rem
rem  NOTE: tpc.exe is a packaged binary and resolves vendor/autoload.php
rem  relative to the CURRENT WORKING DIRECTORY, so we launch it from
rem  the TypePHP root (TPC_ROOT), not from this examples dir. The
rem  finished binary is copied back into this directory.
rem ============================================================
setlocal

rem --- sanity check: are we inside an MSVC environment? ---
where cl.exe >nul 2>&1
if errorlevel 1 (
    echo [ERROR] cl.exe not found on PATH.
    echo         Open the "x64 Native Tools Command Prompt for VS 2022"
    echo         and run this script from there.
    exit /b 1
)

set "TPC_ROOT=D:\git\php\tpc_v1095_windows_x86_64"
set "PHP_HOME=%TPC_ROOT%"
set "PHPX_HOME=%TPC_ROOT%\phpx"
set "PATH=%TPC_ROOT%;%PATH%"

rem --- resolve this script's directory ---
set "SCRIPT_DIR=%~dp0"

pushd "%TPC_ROOT%"
echo [1/2] Running TypePHP compiler (tpc.exe) from %TPC_ROOT%...
"%TPC_ROOT%\tpc.exe" "%SCRIPT_DIR%project.yml" --no-progress
set "RESULT=%errorlevel%"
popd

if not "%RESULT%"=="0" (
    echo.
    echo BUILD FAILED: tpc.exe exited with errorlevel %RESULT%
    exit /b 1
)

rem --- copy the binary back into the example directory ---
copy /Y "%TPC_ROOT%\onepiece_doudizhu.exe" "%SCRIPT_DIR%onepiece_doudizhu.exe" >nul

echo.
echo [2/2] Build finished. Output binary: %SCRIPT_DIR%onepiece_doudizhu.exe
endlocal
