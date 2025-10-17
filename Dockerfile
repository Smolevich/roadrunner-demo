FROM ghcr.io/roadrunner-server/roadrunner:2025.1.3 AS roadrunner
FROM php:8.3-cli

# Copy RoadRunner binary from official image
COPY --from=roadrunner /usr/bin/rr /usr/local/bin/rr

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    zlib1g-dev \
    && docker-php-ext-install zip sockets \
    && pecl install grpc \
    && docker-php-ext-enable grpc \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["rr", "serve", "-c", "rr.yaml"]