FROM php:8.2-apache
RUN apt-get update && apt-get install -y --no-install-recommends libonig-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql mysqli mbstring gd opcache \
    && a2enmod rewrite headers expires deflate \
    && printf 'ServerName localhost\n' > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install exif
RUN printf 'upload_max_filesize=5M\npost_max_size=28M\nmax_file_uploads=6\nmemory_limit=256M\ndisplay_errors=Off\nlog_errors=On\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/lily.ini \
    && mkdir -p /var/lib/fiksitt/request-photos \
    && chown www-data:www-data /var/lib/fiksitt/request-photos \
    && chmod 700 /var/lib/fiksitt/request-photos
WORKDIR /var/www/html
COPY . .
