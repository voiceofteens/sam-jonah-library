# Sam Jonah Library: PHP + Apache container for Render
FROM php:8.3-apache

# MySQL driver for PDO, and Apache .htaccess support
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Render tells the app which port to use through $PORT (10000 by default)
ENV PORT=10000
RUN sed -ri 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf

# Use Ghana time (UTC+0) for PHP date functions
RUN echo "date.timezone=Africa/Accra" > /usr/local/etc/php/conf.d/timezone.ini
RUN echo output_buffering=4096 > /usr/local/etc/php/conf.d/buffer.ini

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000
