@echo off

rem 1. Set environment variables (use system PHP_HOME if defined)
if "%PHP_HOME%"=="" set PHP_HOME=D:\workspace\tpc_v0.6.5_windows_x86_64
set PHPX_HOME=%PHP_HOME%\phpx
set PATH=%PHP_HOME%;%PATH%

rem 2. Initialize MSVC compiler environment
if exist "D:\Program Files\Microsoft Visual Studio\18\Community\VC\Auxiliary\Build\vcvars64.bat" (
    call "D:\Program Files\Microsoft Visual Studio\18\Community\VC\Auxiliary\Build\vcvars64.bat" >nul
) else if exist "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat" (
    call "C:\Program Files\Microsoft Visual Studio\2022\Community\VC\Auxiliary\Build\vcvars64.bat" >nul
) else if exist "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" (
    call "C:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" >nul
) else if exist "D:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" (
    call "D:\Program Files (x86)\Microsoft Visual Studio\2022\BuildTools\VC\Auxiliary\Build\vcvars64.bat" >nul
) else if defined VS_VCVARS64 (
    call "%VS_VCVARS64%" >nul
)

rem 3. Remove Git\usr\bin from PATH if present to avoid GNU link.exe conflict
set PATH=%PATH:C:\Program Files\Git\usr\bin;=%
set PATH=%PATH:D:\Program Files\Git\usr\bin;=%

rem 4. Ensure build directory and sync php.ini
if not exist "%~dp0build" mkdir "%~dp0build"
if exist "%~dp0php.ini" copy /y "%~dp0php.ini" "%~dp0build\php.ini" >nul

rem 5. Run TPC compiler
cd /d %PHP_HOME%
tpc.exe "%~dp0project.yml"
