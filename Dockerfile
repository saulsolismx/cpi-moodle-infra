# ---------------------------------------------------------------------------
# Runtime PHP-FPM para Moodle 5.2 (CPI Virtual)
# El CODIGO de Moodle NO se hornea aqui: se clona en el host con git y se monta
# como volumen, para poder actualizar siguiendo el flujo "Git for Administrators".
# Esta imagen solo aporta PHP + extensiones + ajustes.
# ---------------------------------------------------------------------------
FROM php:8.3-fpm

# Librerias de sistema necesarias para compilar las extensiones de PHP de Moodle,
# mas git (para actualizar Moodle dentro del contenedor) y ghostscript (anotacion PDF).
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
        libicu-dev libonig-dev libzip-dev libxml2-dev \
        libcurl4-openssl-dev libsodium-dev \
        git ghostscript \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        curl gd intl mbstring zip soap exif opcache mysqli sodium \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Ajustes de PHP requeridos/recomendados por Moodle 5.2
COPY php/moodle.ini /usr/local/etc/php/conf.d/zz-moodle.ini

# Entrypoint: asegura permisos del moodledata (volumen) antes de arrancar
COPY moodle-entrypoint.sh /usr/local/bin/moodle-entrypoint.sh
RUN chmod +x /usr/local/bin/moodle-entrypoint.sh

WORKDIR /var/www/html
ENTRYPOINT ["/usr/local/bin/moodle-entrypoint.sh"]
CMD ["php-fpm"]
