FROM dunglas/frankenphp:1-php8.4@sha256:afc8e39e245f0f8084fa47ac55070c9da088bee472fa71ca291f703bdd47f4cc

# CVE-2026-109618 / CVE-2026-109619: reject a vulnerable PHP base.
RUN php -r 'if (PHP_VERSION_ID < 80427) { fwrite(STDERR, "PHP >= 8.4.27 required (CVE-2026-109618, CVE-2026-109619)\n"); exit(1); }' \
    && printf 'session.upload_progress.enabled=0\n' > /usr/local/etc/php/conf.d/zz-php-security.ini

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
