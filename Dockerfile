FROM php:8.2-cli

RUN apt-get update && apt-get install -y \
    git \
    nodejs \
    libpq-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-install pdo pdo_pgsql curl \
    && ln -sf /usr/bin/nodejs /usr/bin/node 2>/dev/null || true \
    && rm -rf /var/lib/apt/lists/*

ENV NODE_BIN=/usr/bin/node

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader

EXPOSE 8080

CMD php -c php.ini -S 0.0.0.0:${PORT:-8080}
