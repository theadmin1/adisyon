FROM node:22-alpine AS frontend

WORKDIR /app
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-fpm-alpine

# Install system dependencies & Nginx & Supervisor
RUN apk add --no-cache nginx supervisor curl git libpng-dev libjpeg-turbo-dev freetype-dev zip libzip-dev sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql gd zip pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .
COPY --from=frontend /app/public/build ./public/build

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
    && chmod -R 775 storage bootstrap/cache database public/uploads

# Copy Nginx & Supervisor configuration
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/adisyon-entrypoint
RUN chmod 0755 /usr/local/bin/adisyon-entrypoint

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/adisyon-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
