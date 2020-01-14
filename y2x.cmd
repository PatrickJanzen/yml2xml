@echo off
if "%1"=="" goto help
if "%1"=="help" goto help
php %~dp0\bin\console y2x %*
goto eof

:help
php %~dp0\bin\console help y2x

:eof