# Use the official PHP image with Apache
FROM php:8.1-apache

# Install system dependencies for PostgreSQL PDO and ImageMagick
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libmagickwand-dev --no-install-recommends \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy your source code to the image
COPY . /var/www/html

# Set the working directory to the Apache web root
WORKDIR /var/www/html

# Set file permissions for the Apache web root
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Expose port 80 to access the web server
EXPOSE 80
