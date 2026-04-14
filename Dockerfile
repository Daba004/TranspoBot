# Use PHP 8.2 with Apache as the base image
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mysqli

# Enable Apache modules for proxying and rewrites
RUN a2enmod proxy proxy_http rewrite headers

# Ensure only mpm_prefork is loaded (Nuclear fix for 'More than one MPM loaded')
# We do this both in the build and in the entrypoint for maximum safety
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \
    && rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork || true

# Set the working directory
WORKDIR /var/www/html

# Copy the entire project for structured access
COPY . .

# Install Python dependencies for the AI engine
RUN pip3 install --no-cache-dir -r ai_engine/requirements.txt --break-system-packages

# Copy Apache configuration to sites-available
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf

# Prepare the entrypoint script
RUN sed -i 's/\r$//' entrypoint.sh && chmod +x entrypoint.sh

# Expose the port (typically 80 or 8080 on Railway)
EXPOSE 80

# Environment variables
ENV PYTHONPATH=/var/www/html/ai_engine

ENTRYPOINT ["./entrypoint.sh"]