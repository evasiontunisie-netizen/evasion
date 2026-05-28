#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${1:-/var/www/evasion}"
SERVICE_FILE="/etc/systemd/system/evasion-websocket.service"

sudo cp deploy/systemd/evasion-websocket.service "$SERVICE_FILE"
sudo sed -i "s#WorkingDirectory=/var/www/evasion#WorkingDirectory=${APP_DIR}#g" "$SERVICE_FILE"
sudo sed -i "s#ExecStart=/usr/bin/php /var/www/evasion/bin/websocket-server.php#ExecStart=/usr/bin/php ${APP_DIR}/bin/websocket-server.php#g" "$SERVICE_FILE"
sudo systemctl daemon-reload
sudo systemctl enable evasion-websocket
sudo systemctl restart evasion-websocket
sudo systemctl status evasion-websocket --no-pager
