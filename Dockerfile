FROM php:8.1-apache
RUN apt-get update && apt-get install -y \
    unzip git libcurl4-openssl-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring curl xml zip bcmath opcache \
    && a2enmod rewrite expires headers deflate \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && chmod -R 775 /var/www/html/uploads /var/www/html/logs
EXPOSE 80
