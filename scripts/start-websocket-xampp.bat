@echo off
cd /d "%~dp0.."
echo Starting Evasion ERP WebSocket on ws://localhost:8090
php bin\websocket-server.php
pause
