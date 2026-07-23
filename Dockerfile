FROM php:8.3-fpm-alpine

# Install system dependencies & Nginx & Supervisor
RUN apk add --no-cache nginx supervisor curl git libpng-dev libjpeg-turbo-dev freetype-dev zip libzip-dev sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Configure PHP upload limits
RUN echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

# Set permissions and create sqlite database & uploads folder
RUN touch database/database.sqlite \
    && mkdir -p public/uploads/products \
    && chown -R www-data:www-data storage bootstrap/cache database public/uploads \
    && chmod -R 777 storage bootstrap/cache database public/uploads

# Copy Nginx & Supervisor configuration
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
