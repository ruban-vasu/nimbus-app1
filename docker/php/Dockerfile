FROM php:8.3-fpm

# ── Non-root user ──────────────────────────────────────────────────────────
# Create a dedicated application user/group (UID/GID 1000) so the container
# never runs as root. Matches the typical host developer UID to avoid
# bind-mount permission issues on Linux.
ARG APP_UID=1000
ARG APP_GID=1000

RUN groupadd --gid "$APP_GID" laravel \
    && useradd --uid "$APP_UID" --gid laravel --no-create-home --shell /bin/bash laravel

# ── System dependencies ────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ─────────────────────────────────────────────────────────
# pdo_mysql  — MySQL database driver
# bcmath     — arbitrary-precision math (required by Laravel/payments)
# mbstring   — multibyte string handling
# exif       — image metadata
# pcntl      — process control (queues/signals)
# gd         — image processing
# zip        — archive support
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    bcmath \
    mbstring \
    exif \
    pcntl \
    gd \
    zip

# ── OPcache ────────────────────────────────────────────────────────────────
RUN docker-php-ext-enable opcache

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# ── Redis extension (PECL) ─────────────────────────────────────────────────
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear

# ── Composer (rootless copy) ───────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Application ────────────────────────────────────────────────────────────
WORKDIR /var/www

# Copy composer manifests first so this layer is cached unless deps change
COPY --chown=laravel:laravel composer.json composer.lock ./

# Install dependencies as root (writes to vendor/); ownership fixed below
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy the rest of the application source
COPY --chown=laravel:laravel . .

# Re-run post-install scripts now that full source is present
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Laravel writable directories — owned by the app user, not root
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R laravel:laravel storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# ── Custom PHP runtime settings ────────────────────────────────────────────
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# ── Switch to non-root user ────────────────────────────────────────────────
USER laravel

EXPOSE 9000

CMD ["php-fpm"]
