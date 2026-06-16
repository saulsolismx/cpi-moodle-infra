#!/bin/sh
# Asegura que el directorio de datos (volumen) sea escribible por el usuario web.
# Se ejecuta como root (master de php-fpm); los workers corren como www-data.
set -e

mkdir -p /var/www/moodledata
chown www-data:www-data /var/www/moodledata

exec "$@"
