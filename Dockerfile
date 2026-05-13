FROM php:8.4-cli-alpine AS base

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

FROM base AS rr-downloader
ARG TARGETARCH
RUN ARCH=$([ "$TARGETARCH" = "arm64" ] && echo "linux-arm64" || echo "linux-amd64") && \
    RR_VERSION="2025.1.13" && \
    curl -L "https://github.com/roadrunner-server/roadrunner/releases/download/v${RR_VERSION}/roadrunner-${RR_VERSION}-${ARCH}.tar.gz" \
    | tar -xz --strip-components=1 -C /usr/local/bin "roadrunner-${RR_VERSION}-${ARCH}/rr"

FROM base AS codegen

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts

COPY .jane-openapi.php lifestream-api.yaml ./
RUN vendor/bin/jane-openapi generate

FROM base AS builder

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .
COPY --from=codegen /app/app/Lifestream ./app/Lifestream

RUN composer dump-autoload --optimize --no-dev --no-scripts

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
