@echo off

rem **************************************************************************
rem ** the symfony build script for Windows based systems (based on phing.bat)
rem ** $Id$
rem **************************************************************************

rem This script will do the following:
rem - check for PHP_COMMAND env, if found, use it.
rem   - if not found detect php, if found use it, otherwise err and terminate
rem - check for SYMFONY_HOME evn, if found use it
rem   - if not found, use a sensible default

if "%OS%"=="Windows_NT" @setlocal

rem %~dp0 is expanded pathname of the current script under NT
set DEFAULT_SYMFONY_HOME=%~dp0

goto init
goto cleanup

:init

if "%SYMFONY_HOME%" == "" set SYMFONY_HOME="@DATA-DIR@\symfony"

if "%PHP_COMMAND%" == "" goto no_phpcommand

goto run

:run
IF EXIST "@DATA-DIR@" (
  %PHP_COMMAND% -d html_errors=off "%SYMFONY_HOME%\bin\symfony.php" %1 %2 %3 %4 %5 %6 %7 %8 %9
) ELSE (
  %PHP_COMMAND% -d html_errors=off "%DEFAULT_SYMFONY_HOME%\symfony.php" %1 %2 %3 %4 %5 %6 %7 %8 %9
)
goto cleanup

:no_phpcommand
REM echo ------------------------------------------------------------------------
REM echo WARNING: Set environment var PHP_COMMAND to the location of your php.exe
REM echo          executable (e.g. C:\PHP\php.exe).  (Assuming php.exe on Path)
REM echo ------------------------------------------------------------------------
set PHP_COMMAND=php.exe
goto init

:cleanup
if "%OS%"=="Windows_NT" @endlocal
REM pause
