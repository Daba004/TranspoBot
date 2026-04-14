#!/bin/bash

# Start FastAPI in the background
echo "Starting FastAPI backend..."
cd /app/ai_engine
python3 -m uvicorn main:app --host 127.0.0.1 --port 8000 &

# Start Apache in the foreground
echo "Starting Apache frontend on port $PORT..."
# Railway passes the port to listen on as $PORT. 
# We need to tell Apache to listen on this port.
sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:$PORT>/g" /etc/apache2/sites-available/000-default.conf

apache2-foreground
