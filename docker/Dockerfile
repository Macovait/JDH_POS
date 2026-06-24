FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    redis \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    intl \
    mbstring \
    bcmath

# Install Redis extension
RUN pecl install redis-6.0.2 && docker-php-ext-enable redis

# Get Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --optimize-autoloader --no-dev

# Build assets
RUN if [ -f "package.json" ]; then \
    npm ci --production; \
    npm run build; \
    fi

# Create storage directories
RUN mkdir -p storage/{app,framework/{cache,sessions,testing,views},logs} \
    && chmod -R 775 storage \
    && chmod +x scripts/*.php \
    && chown -R www-data:www-data .

# Expose port 9000
EXPOSE 9000

CMD ["php-fpm"]