FROM php:8.5-fpm

WORKDIR /var/app

RUN apt-get update && apt-get upgrade -y \
    && apt-get install apt-utils -y

RUN apt-get update && apt-get install -y procps

RUN apt-get update && apt-get install -y \
    build-essential \
    libwebp-dev libjpeg62-turbo-dev libpng-dev libxpm-dev libzip-dev libpq-dev libgmp-dev libffi-dev libssl-dev \
    libfreetype6 libfreetype6-dev \
    locales \
    jpegoptim optipng pngquant gifsicle \
    zip unzip \
    git \
    curl

RUN pecl install redis && docker-php-ext-enable redis

# Расширения для работы с базой данных + gd конфигурация для работы captha
RUN docker-php-ext-install pdo pdo_mysql mysqli
RUN docker-php-ext-install gd \
      && docker-php-ext-configure gd --with-freetype --with-jpeg \
	  && docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-install pcntl

RUN pecl install xdebug && docker-php-ext-enable xdebug

# Install PHP Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer


RUN docker-php-source delete
RUN apt-get autoremove --purge -y && apt-get autoclean -y && apt-get clean -y

RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

