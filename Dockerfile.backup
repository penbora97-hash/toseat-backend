FROM php:8.2-cli

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_pgsql

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# ✅ Copy everything first
COPY . .

# ✅ Debug: Show files
RUN ls -la
RUN cat composer.json || echo "composer.json not found"

# ✅ Install with --no-scripts
RUN composer install --no-interaction --no-dev --optimize-autoloader --no-scripts

# ✅ Run scripts separately
RUN php artisan key:generate

# Set permissions
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]