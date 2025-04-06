@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../laravel/octane/bin/swoole-server
php "%BIN_TARGET%" %*
