FROM php:8.3-apache

# Install required system packages for Composer and ZIP
RUN apt-get update && apt-get install -y unzip git \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'expose_php = Off'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'error_log = /dev/stderr'; \
        echo 'date.timezone = Asia/Manila'; \
    } > "$PHP_INI_DIR/conf.d/lazyledger.ini"

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
    && echo 'ServerTokens Prod' >> /etc/apache2/apache2.conf \
    && echo 'ServerSignature Off' >> /etc/apache2/apache2.conf

# Bring in Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app
COPY . /var/www/app

# Install the Brevo SDK (this generates the vendor/autoload.php file)
RUN composer require brevo/brevo-php guzzlehttp/guzzle

RUN chown -R www-data:www-data /var/www/app

ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]