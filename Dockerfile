FROM dunglas/frankenphp:1-php8.3

# Extensions PHP nécessaires pour Symfony + PostgreSQL
RUN install-php-extensions \
    pdo_pgsql \
    intl \
    zip \
    opcache \
    apcu

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Node 20 pour Tailwind/Stimulus
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

WORKDIR /app
