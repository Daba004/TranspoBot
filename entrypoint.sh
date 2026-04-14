#!/bin/bash
set -e

echo "[BOOT] Starting TranspoBot Services..."

# 1. Nuclear MPM fix (Double safety)
echo "[BOOT] Cleaning Apache MPMs..."
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
a2enmod mpm_prefork || true

# 2. Start FastAPI in the background
echo "[BOOT] Starting FastAPI backend on 127.0.0.1:8000..."
cd /var/www/html/ai_engine
python3 -m uvicorn main:app --host 127.0.0.1 --port 8000 > /var/log/fastapi.log 2>&1 &

# 3. Configure Apache Port
TARGET_PORT=${PORT:-80}
echo "[BOOT] Configuring Apache to listen on port $TARGET_PORT..."

sed -i "s/Listen 80/Listen $TARGET_PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$TARGET_PORT>/g" /etc/apache2/sites-available/000-default.conf

# 4. Start Apache in the foreground
echo "[BOOT] Launching Apache (Foreground)..."
exec apache2-foreground
