# Используем официальный PHP образ
FROM php:8.4-fpm

# Устанавливаем необходимые расширения и утилиты для Laravel
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ARG UID=1000
ARG GID=1000
RUN groupmod -g ${GID} www-data && usermod -u ${UID} -g ${GID} www-data

# Установка Laravel
WORKDIR /var/www
