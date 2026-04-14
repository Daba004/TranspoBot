# Use PHP 8.2 with Apache as the base image
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    libapache2-mod-proxy-html \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mysqli

# Enable Apache modules for proxying
RUN a2enmod proxy proxy_http

# Set the working directory
WORKDIR /var/www/html

# Copy the frontend files
COPY public/ .

# Prepare AI Engine directory
RUN mkdir -p /app/ai_engine
WORKDIR /app/ai_engine

# Copy the backend files
COPY ai_engine/ .

# Install Python dependencies
RUN pip3 install --no-cache-dir -r requirements.txt --break-system-packages

# Copy Apache configuration
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Create an entrypoint script to start both services
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose the port Railway provides via $PORT
EXPOSE 80

# Environment variables
ENV PORT=80
ENV PYTHONPATH=/app/ai_engine

ENTRYPOINT ["entrypoint.sh"]
