FROM php:8.1-cli
RUN apt-get update && apt-get install -y git unzip curl net-tools
RUN curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
WORKDIR /var/www/websocket
COPY websocket_php/composer.json ./
RUN composer install --no-interaction --no-progress --prefer-dist --ignore-platform-reqs
COPY websocket_php/. ./
EXPOSE 8081
CMD ["php", "server.php"]
