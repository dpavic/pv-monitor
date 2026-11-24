FROM php:8.3-apache

# PDO driver for MySQL
RUN docker-php-ext-install pdo_mysql

# Production php.ini as base: errors are logged, never displayed.
# (bootstrap.php turns the display on only for APP_ENV=development)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && echo "expose_php = Off" > "$PHP_INI_DIR/conf.d/security.ini"

# mod_headers is needed for the security headers
RUN a2enmod headers
COPY security.conf /etc/apache2/conf-enabled/zz-security.conf

# Serve only the /public folder, so src/, bin/ and .env are never reachable via HTTP
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# The plant is located in Germany, so all timestamps are local German time
ENV TZ=Europe/Berlin
RUN echo "date.timezone = Europe/Berlin" > "$PHP_INI_DIR/conf.d/timezone.ini"

WORKDIR /var/www/html
