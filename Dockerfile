FROM php:8.2-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev postgresql-client \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/vida-salud-entrypoint

RUN chmod +x /usr/local/bin/vida-salud-entrypoint /var/www/html/scripts/inicializar-db.sh \
    && chown -R www-data:www-data /var/www/html/storage

EXPOSE 10000

CMD ["/usr/local/bin/vida-salud-entrypoint"]
