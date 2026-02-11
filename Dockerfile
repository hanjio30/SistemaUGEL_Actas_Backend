FROM php:8.3-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql pgsql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy composer files first
COPY composer.json composer.lock ./

# Install dependencies (sin ejecutar scripts todavía)
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Copy application files
COPY . .

# Complete the composer installation
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Configure Apache DocumentRoot
RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

EXPOSE 80

# Start command
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan migrate --force && \
    apache2-foreground