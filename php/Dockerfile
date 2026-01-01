FROM php:8.3-cli
RUN docker-php-ext-install pdo pdo_mysql
WORKDIR /app
COPY index.php /app/index.php
EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "/app/index.php"]
