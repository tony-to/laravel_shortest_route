#start with our base image (the foundation) - version 7.1.5
FROM php:7.1.5-fpm

#install all the system dependencies and enable PHP modules 
RUN apt-get update && apt-get install -y \
      libicu-dev \
      libpq-dev \
      libmcrypt-dev \
      git \
      zip \
      unzip \
    && rm -r /var/lib/apt/lists/* \
    && docker-php-ext-configure pdo_mysql --with-pdo-mysql=mysqlnd \
    && docker-php-ext-install \
      intl \
      mbstring \
      mcrypt \
      pcntl \
      pdo_mysql \
      pdo_pgsql \
      pgsql \
      zip \
      opcache

#install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

ENV APP_HOME /var/www

COPY . $APP_HOME

RUN composer install --no-interaction -d=/var/www

RUN chown -R www-data:www-data /var/www/

RUN php /var/www/artisan optimize
