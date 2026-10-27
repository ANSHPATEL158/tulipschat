FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo_mysql pdo_pgsql \
    && a2enmod rewrite \
    && printf "file_uploads=On\nupload_max_filesize=50M\npost_max_size=60M\nmax_file_uploads=10\nmax_execution_time=120\nmax_input_time=120\nmemory_limit=256M\n" > /usr/local/etc/php/conf.d/uploads.ini \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80