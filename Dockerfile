FROM php:8.3-apache

# Driver PostgreSQL pour PHP (PDO)
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Le site + le schéma de la base (hors du dossier web)
COPY app/ /var/www/html/
COPY init.sql /opt/ecole/init.sql
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh \
    && chown -R www-data:www-data /var/www/html

ENV PORT=10000
EXPOSE 10000
CMD ["docker-entrypoint.sh"]
