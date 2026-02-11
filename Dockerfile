FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpq-dev \
    zip \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev

# Instalar extensiones necesarias
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN a2enmod rewrite

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache

RUN sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD php artisan migrate --force && apache2-foreground
