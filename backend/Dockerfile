# syntax=docker/dockerfile:1.4
# Trail Service - PHP + Apache Container
# Matches production server setup (PHP 8.4 + Apache + MariaDB)
#
# IMPORTANT: Production has NO Composer access (FTP only)
# - vendor/ directory is generated here and uploaded via FTP
# - See DEPLOYMENT.md for deployment strategy

ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-apache

LABEL maintainer="kibotu"
LABEL description="Trail service - Link sharing with Google OAuth"

# Re-declare ARG after FROM for use in RUN commands
ARG PHP_VERSION

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    libicu-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    mariadb-client \
    unzip \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Configure GD extension
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

# Install PHP extensions to match production
# Based on phpinfo from production server
RUN docker-php-ext-install -j"$(nproc)" \
    gd \
    mysqli \
    pdo_mysql \
    mbstring \
    exif \
    zip \
    bcmath \
    intl \
    opcache \
    soap \
    sockets \
    calendar \
    gettext \
    iconv

# Note: The following are built-in to PHP 8.4:
# - curl (enabled by default)
# - openssl (enabled by default)
# - json (enabled by default)
# - ctype (enabled by default)
# - fileinfo (enabled by default)
# - sodium (enabled by default)
# - xml, dom, simplexml (enabled by default)

# Enable Apache modules
RUN a2enmod rewrite headers expires

# Configure Apache DocumentRoot to point to public directory
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Configure Apache for .htaccess support
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Set recommended PHP settings for development
RUN { \
    echo 'display_errors = On'; \
    echo 'display_startup_errors = On'; \
    echo 'error_reporting = E_ALL'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/log/php_errors.log'; \
    echo 'memory_limit = 256M'; \
    echo 'upload_max_filesize = 10M'; \
    echo 'post_max_size = 10M'; \
    echo 'max_execution_time = 60'; \
    echo 'max_input_time = 60'; \
    } > /usr/local/etc/php/conf.d/trail-dev.ini

# Configure OPcache for performance
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.validate_timestamps=1'; \
    echo 'opcache.jit=1255'; \
    echo 'opcache.jit_buffer_size=100M'; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first for better layer caching
COPY composer.json composer.lock* ./

# Install dependencies
# Note: Use --no-dev for production builds
RUN composer install \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader \
    && composer clear-cache

# Copy application code
COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
