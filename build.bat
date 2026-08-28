@echo off

rem 1. Set environment variables
if not defined PHP_HOME set "PHP_HOME=D:\workspace\tpc_v0.6.5_windows_x86_64"
if "%PHP_HOME:~-1%"=="\" set "PHP_HOME=%PHP_HOME:~0,-1%"

if exist "%PHP_HOME%\phpx" (
    set "PHPX_HOME=%PHP_HOME%\phpx"
) else (
    set "PHPX_HOME=%~dp0vendor\swoole\phpx"
)
set "PATH=%PHP_HOME%;%PATH%"

rem 2. Initialize MSVC compiler environment (skip if cl.exe already available)
where cl.exe >nul 2>nul
if %ERRORLEVEL% neq 0 (
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
)

rem 3. Remove Git\usr\bin from PATH if present to avoid GNU link.exe conflict
set "PATH=%PATH:C:\Program Files\Git\usr\bin;=%"
set "PATH=%PATH:D:\Program Files\Git\usr\bin;=%"

rem 4. Ensure build directory and sync php.ini
if not exist "%~dp0build" mkdir "%~dp0build"
if exist "%~dp0php.ini" copy /y "%~dp0php.ini" "%~dp0build\php.ini" >nul

rem 4.1 Ensure PHPX library is compiled on Windows
if not exist "%PHPX_HOME%\lib\phpx.lib" if not exist "%PHPX_HOME%\phpx.lib" (
    echo [INFO] Compiling PHPX static library (phpx.lib) for MSVC...
    if exist "%PHPX_HOME%" (
        pushd "%PHPX_HOME%"
        if not exist "build" mkdir "build"
        cd build
        cmake .. -G "NMake Makefiles" -Dphp_dir="%PHP_HOME%" -DBUILD_TESTS=OFF -DBUILD_EXT=OFF
        if %ERRORLEVEL% equ 0 (
            nmake phpx
            if exist "phpx.lib" (
                if not exist "%PHPX_HOME%\lib" mkdir "%PHPX_HOME%\lib"
                copy /y "phpx.lib" "%PHPX_HOME%\lib\" >nul
                copy /y "phpx.lib" "%PHPX_HOME%\" >nul
            )
        ) else (
            cmake .. -Dphp_dir="%PHP_HOME%" -DBUILD_TESTS=OFF -DBUILD_EXT=OFF
            cmake --build . --config Release --target phpx
            if exist "Release\phpx.lib" (
                if not exist "%PHPX_HOME%\lib" mkdir "%PHPX_HOME%\lib"
                copy /y "Release\phpx.lib" "%PHPX_HOME%\lib\" >nul
                copy /y "Release\phpx.lib" "%PHPX_HOME%\" >nul
            )
        )
        popd
    )
)

rem 5. Run TPC compiler
echo [INFO] Compiling webman-server with TPC (PHP_HOME=%PHP_HOME% PHPX_HOME=%PHPX_HOME%)...
cd /d "%~dp0"
if exist "%PHP_HOME%\tpc.exe" (
    "%PHP_HOME%\tpc.exe" "%~dp0project.windows.yml"
) else if exist "%~dp0vendor\bin\tpc.php" (
    php "%~dp0vendor\bin\tpc.php" "%~dp0project.windows.yml"
) else if exist "%~dp0vendor\swoole\typephp\bin\tpc.php" (
    php "%~dp0vendor\swoole\typephp\bin\tpc.php" "%~dp0project.windows.yml"
) else (
    echo [ERROR] Cannot find tpc compiler in %PHP_HOME% or vendor/bin!
    exit /b 1
)
exit /b %ERRORLEVEL%
