FROM dunglas/frankenphp:1-php8.4

# CVE-2026-109618 / CVE-2026-109619: refuse a vulnerable cached or upstream base.

# Extensions PHP nécessaires pour Symfony + PostgreSQL
RUN php -r 'if (PHP_VERSION_ID < 80427) { fwrite(STDERR, "PHP >= 8.4.27 required (CVE-2026-109618, CVE-2026-109619)\n"); exit(1); }' \
    && install-php-extensions \
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
