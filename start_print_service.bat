@echo off

:: ─── Open a persistent CMD window if not already inside one ──────────────────
:: This ensures the window never closes even on double-click
if "%CMDLVL%"=="" (
    start "Sheba POS Print Service" cmd /k ""%~f0""
    exit
)

:: ─── Now running inside the persistent CMD window ─────────────────────────────
cd /d "%~dp0"
title Sheba POS Auto Print Service
color 0B

echo.
echo ===================================================
echo    Sheba Restaurant Auto Print Service
echo    Press Ctrl+C to stop.
echo ===================================================
echo.

set "PHP_BIN="

:: Check common XAMPP and custom PHP paths
if exist "C:\xampp3\php\php.exe" set "PHP_BIN=C:\xampp3\php\php.exe" & goto found
if exist "C:\xampp\php\php.exe"  set "PHP_BIN=C:\xampp\php\php.exe"  & goto found
if exist "D:\xampp\php\php.exe"  set "PHP_BIN=D:\xampp\php\php.exe"  & goto found
if exist "D:\xampp3\php\php.exe" set "PHP_BIN=D:\xampp3\php\php.exe" & goto found
if exist "E:\xampp\php\php.exe"  set "PHP_BIN=E:\xampp\php\php.exe"  & goto found
if exist "E:\xampp3\php\php.exe" set "PHP_BIN=E:\xampp3\php\php.exe" & goto found
if exist "C:\php\php.exe"        set "PHP_BIN=C:\php\php.exe"        & goto found

:: Fallback: Try php from system PATH
where php >nul 2>nul
if %ERRORLEVEL% equ 0 set "PHP_BIN=php" & goto found

echo.
echo  *** [ERROR] PHP not found! ***
echo.
echo  Please check:
echo    1. Is XAMPP installed? (C:\xampp or C:\xampp3 or D:\xampp)
echo    2. What drive is XAMPP on? (type: dir C:\ D:\ E:\ to check)
echo    3. Add php.exe to system PATH
echo.
pause
exit /b 1

:found
echo [+] PHP Found: %PHP_BIN%
echo.

:: Verify local_print_worker.php exists
if not exist "local_print_worker.php" (
    echo.
    echo  *** [ERROR] local_print_worker.php not found! ***
    echo  Current folder: %CD%
    echo  Make sure all files are copied to this same folder.
    echo.
    pause
    exit /b 1
)

echo [+] Worker file ready.
echo [+] Starting print monitoring loop... (Ctrl+C to stop)
echo.

:loop
"%PHP_BIN%" -f local_print_worker.php
echo.
echo [!] Worker stopped. Restarting in 5 seconds...
ping 127.0.0.1 -n 6 >nul
goto loop
