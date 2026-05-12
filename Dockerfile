FROM php:8.2-cli-alpine AS base

RUN apk add --no-cache \
    linux-headers \
    $PHPIZE_DEPS \
    curl \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql bcmath opcache zip pcntl sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# Install RoadRunner (architecture-aware)
FROM base AS rr-downloader
ARG TARGETARCH
RUN ARCH=$([ "$TARGETARCH" = "arm64" ] && echo "linux-arm64" || echo "linux-amd64") && \
    RR_VERSION="2025.1.13" && \
    curl -L "https://github.com/roadrunner-server/roadrunner/releases/download/v${RR_VERSION}/roadrunner-${RR_VERSION}-${ARCH}.tar.gz" \
    | tar -xz --strip-components=1 -C /usr/local/bin "roadrunner-${RR_VERSION}-${ARCH}/rr"

FROM base AS builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .

# Generate autoload only — artisan cache commands require DB and .env, run at container start
RUN composer dump-autoload --optimize --no-dev

FROM base AS production

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=rr-downloader /usr/local/bin/rr /usr/local/bin/rr
COPY --from=builder /app /app

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/rr \
    && chmod +x /usr/local/bin/docker-entrypoint.sh \
    && ln -sf /usr/local/bin/rr /app/rr

WORKDIR /app

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
