#!/bin/bash
set -e

echo "[BOOT] Starting TranspoBot Services..."

# 1. Start FastAPI in the background
echo "[BOOT] Starting FastAPI backend on 127.0.0.1:8000..."
cd /app/ai_engine
python3 -m uvicorn main:app --host 127.0.0.1 --port 8000 > /var/log/fastapi.log 2>&1 &

# Wait a second to ensure it doesn't crash immediately
sleep 1

# 2. Configure Apache Port
# Railway sets the $PORT environment variable.
TARGET_PORT=${PORT:-80}
echo "[BOOT] Configuring Apache to listen on port $TARGET_PORT..."

# Update ports.conf
if [ -f /etc/apache2/ports.conf ]; then
    sed -i "s/Listen 80/Listen $TARGET_PORT/g" /etc/apache2/ports.conf
fi

# Update 000-default.conf
if [ -f /etc/apache2/sites-available/000-default.conf ]; then
    sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$TARGET_PORT>/g" /etc/apache2/sites-available/000-default.conf
fi

# 3. Start Apache in the foreground
echo "[BOOT] Launching Apache (Foreground)..."
exec apache2-foreground
