FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libsodium-dev \
    git \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql \
    && docker-php-ext-enable sodium

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

EXPOSE 80
