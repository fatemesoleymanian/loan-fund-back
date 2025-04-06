@ECHO OFF
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../laravel/octane/bin/roadrunner-worker
php "%BIN_TARGET%" %*
