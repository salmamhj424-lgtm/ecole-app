#!/bin/sh
set -e
# Render fournit le port dans $PORT : Apache doit écouter dessus
PORT="${PORT:-10000}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
# Crée les tables et les données de démo si la base est vide
php /var/www/html/bootstrap.php || echo "⚠ bootstrap a échoué (voir logs)"
exec apache2-foreground
