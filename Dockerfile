FROM php:8.2-apache

# Install extensions and dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    git \
    default-mysql-client \
    libpq-dev \
    && docker-php-ext-install zip pdo pdo_mysql \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create both possible document roots and link them
RUN composer require aws/aws-sdk-php

RUN mkdir -p /var/www/html
RUN mkdir -p /var/www/localhost/htdocs
RUN ln -sf /var/www/html /var/www/localhost/htdocs

# Set working directory
WORKDIR /var/www/html

# Copy composer files FIRST
COPY composer.json ./

# Install dependencies
RUN composer install --no-scripts

# Copy application code
COPY src/ ./

# Modify Database.php to use the absolute path to autoload
RUN sed -i 's|require __DIR__ . '"'"'/../vendor/autoload.php'"'"'|require '"'"'/var/www/html/vendor/autoload.php'"'"'|' /var/www/html/Database.php || echo "Failed to update Database.php"

# Create .htaccess
RUN echo "RewriteEngine On" > .htaccess && \
    echo "RewriteCond %{REQUEST_FILENAME} !-f" >> .htaccess && \
    echo "RewriteCond %{REQUEST_FILENAME} !-d" >> .htaccess && \
    echo "RewriteRule ^(.*)$ index.php [L,QSA]" >> .htaccess

# Set permissions
RUN chown -R www-data:www-data /var/www

# Configure Apache
RUN a2enmod rewrite headers
COPY apache-site.conf /etc/apache2/sites-available/000-default.conf

# Simple health check
HEALTHCHECK --interval=5s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:$PORT || exit 1
  
# Show file structure for debugging
RUN echo "FINAL STRUCTURE:" && \
    ls -la /var/www/html && \
    ls -la /var/www/localhost/htdocs && \
    ls -la /var/www/html/vendor || echo "NO VENDOR DIR!"

EXPOSE 80