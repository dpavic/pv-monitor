FROM php:8.3-apache

# PDO driver for MySQL
RUN docker-php-ext-install pdo_mysql

# mod_headers is needed later for security headers
RUN a2enmod headers

# Serve only the /public folder, so src/, bin/ and .env are never reachable via HTTP
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# The plant is located in Germany, so all timestamps are local German time
ENV TZ=Europe/Berlin
RUN echo "date.timezone = Europe/Berlin" > /usr/local/etc/php/conf.d/timezone.ini

WORKDIR /var/www/html
