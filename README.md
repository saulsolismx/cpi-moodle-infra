# CPI Virtual — Moodle 5.2 en Docker (VPS)

Stack listo para producción a tu escala (≈7 cursos, ~30 alumnos):

```
Caddy (TLS automático)  ─►  PHP-FPM 8.3 (Moodle 5.2)  ─►  MariaDB 11.4
                                     │
                                     └─►  Redis (sesiones + caché)
                            +  contenedor cron (cada 60 s)
```

El código de Moodle se gestiona con **git** (flujo "Git for Administrators"), por lo
que las actualizaciones futuras son `git fetch` + `git checkout` + script de upgrade.

---

## 0) Antes de empezar

- **VPS:** Vultr Ciudad de México (Querétaro), 2 vCPU / 4 GB RAM / 100 GB NVMe. SO sugerido **Rocky Linux** (o Ubuntu 22.04+; el stack corre igual en ambos).
- **DNS:** crea un registro **A** de `campus.cpivirtual.com` apuntando a la **IP pública** del VPS. Deja intacto tu sitio actual en `cpivirtual.com`.
- **Firewall:** abre los puertos **80** y **443** (Caddy los necesita para emitir el certificado y servir el sitio).

> El subdominio dedicado es obligatorio desde Moodle 5.1: el campus debe servirse solo a sí mismo.

---

## 1) Instalar Docker en el VPS

**Rocky Linux:**
```bash
sudo dnf -y install dnf-plugins-core
sudo dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo
sudo dnf -y install docker-ce docker-ce-cli containerd.io docker-compose-plugin
sudo systemctl enable --now docker
```

**Ubuntu:**
```bash
curl -fsSL https://get.docker.com | sudo sh
sudo systemctl enable --now docker
```

---

## 2) Traer estos archivos y el código de Moodle

Copia el contenido de este bundle (Dockerfile, docker-compose.yml, Caddyfile, php/, etc.) a una carpeta en el VPS, por ejemplo `/opt/cpi-moodle/`. Luego, **dentro de esa carpeta**, clona Moodle 5.2:

```bash
cd /opt/cpi-moodle

# Clona el repositorio oficial
git clone https://github.com/moodle/moodle.git moodle
cd moodle

# Cambia a la ÚLTIMA versión estable 5.2 (revisa el tag más reciente)
git fetch --tags
git tag -l 'v5.2.*'          # mira cuál es el último, p.ej. v5.2.1
git checkout v5.2.1          # ← usa el tag más reciente que viste
cd ..
```

> Usar un **tag** (no la rama) es la recomendación oficial para producción.

Da la propiedad del código y los datos al usuario web del contenedor (uid 33 = www-data):
```bash
sudo chown -R 33:33 moodle
```

---

## 3) Configurar secretos

```bash
cp .env.example .env
nano .env      # pon tu dominio, correo y contraseñas largas
```

---

## 4) Construir y levantar

```bash
docker compose build
docker compose up -d
docker compose ps      # db debe quedar "healthy"
```

Caddy intentará emitir el certificado TLS; necesita que el DNS ya resuelva y los puertos 80/443 abiertos.

---

## 5) Instalar Moodle (CLI, no interactivo)

```bash
docker compose exec -u www-data php sh -c 'php /var/www/html/admin/cli/install.php \
  --non-interactive --agree-license --lang=es \
  --wwwroot="$MOODLE_WWWROOT" \
  --dataroot=/var/www/moodledata \
  --dbtype=mariadb --dbhost=db \
  --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" \
  --fullname="CPI Virtual" --shortname="CPI" \
  --adminuser=admin --adminpass="$ADMIN_PASSWORD" --adminemail="$ADMIN_EMAIL"'
```

Esto crea `moodle/config.php` y arma toda la base de datos.

---

## 6) Activar Redis (sesiones + HTTPS detrás de Caddy)

Abre `moodle/config.php` y pega el contenido de **`config-extra.php`** (sin la etiqueta `<?php`)
justo **antes** de la línea `require_once(__DIR__ . '/lib/setup.php');`

```bash
sudo nano moodle/config.php
```

Reinicia para aplicar:
```bash
docker compose restart php cron
```

Ya puedes entrar a **https://campus.cpivirtual.com** con `admin` y tu `ADMIN_PASSWORD`.

> **Caché de aplicación en Redis (opcional, recomendado):** en el sitio →
> *Administración del sitio › Plugins › Cachés › Configuración* → agrega un almacén
> Redis (`redis:6379`) y asígnalo a *Aplicación* y *Sesión*.

---

## 7) Migrar tus cursos desde welearning

welearning es Moodle de marca blanca, así que tienes dos rutas (en orden de preferencia):

1. **Respaldo de curso `.mbz` (ideal):** en welearning, en cada curso →
   *Respaldo*. Descarga el `.mbz`. En tu Moodle nuevo: *Administración del sitio ›
   Cursos › Restaurar curso* y sube el `.mbz`. Conserva exámenes, banco de preguntas,
   estructura y calificaciones.
2. **SCORM (si no te dejan respaldar):** exporta el paquete SCORM y agrégalo en un
   curso como actividad *Paquete SCORM*.

> **Antes de avisar que te vas**, verifica que puedas descargar los respaldos tú mismo,
> y revisa en tu contrato quién es dueño de los contenidos que welearning te produjo.

---

## 8) Cobro por curso (pago único)

*Administración del sitio › Plugins › Métodos de matriculación* → habilita
**"Inscripción mediante pago"**. Configura la pasarela en *Administración del sitio ›
Pagos*: PayPal viene nativo y opera en México; para Stripe/Conekta/Mercado Pago se
instala un plugin de pasarela (lo vemos cuando llegues a esta fase). Luego, en cada
curso, agregas el método "pago", pones el precio y el alumno queda inscrito al pagar.

---

## 9) Respaldos (hazlos automáticos)

```bash
# Base de datos
docker compose exec -T db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' > backup_db_$(date +%F).sql

# Datos (archivos subidos, etc.)
docker run --rm -v cpi-moodle_moodledata:/data -v "$PWD":/backup alpine \
  tar czf /backup/backup_moodledata_$(date +%F).tar.gz -C /data .
```
Programa ambos en `cron` del host y guarda copias fuera del VPS.

---

## 10) Actualizar Moodle a futuro (flujo git)

```bash
docker compose exec -u www-data php sh -c '
  cd /var/www/html &&
  git fetch --tags &&
  git checkout vX.Y.Z'                       # nuevo tag 5.2.x o mayor
docker compose exec -u www-data php php /var/www/html/admin/cli/upgrade.php --non-interactive
```
**Siempre** respalda (paso 9) antes de actualizar.

---

## Comandos útiles

```bash
docker compose logs -f php          # ver logs de Moodle/PHP
docker compose logs -f caddy        # ver emisión del certificado TLS
docker compose exec -u www-data php php admin/cli/cron.php   # cron manual
docker compose down                 # apagar (conserva volúmenes/datos)
```
