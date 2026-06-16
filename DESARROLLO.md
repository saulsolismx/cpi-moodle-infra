# Guía de desarrollo — CPI Virtual (Moodle)

Este repo contiene la **infraestructura como código** del campus. No contiene el core de
Moodle ni el contenido de los cursos (ver más abajo).

---

## Correr en local (Mac / Docker Desktop)

Usa los mismos archivos que en producción; solo cambian dos valores en `.env`:

```
MOODLE_DOMAIN=localhost
MOODLE_WWWROOT=https://localhost
```

Luego:

```bash
cp .env.example .env          # ajusta los valores (incl. los dos de arriba)
# clona el core de Moodle (ver paso 2 del README) y: sudo chown -R 33:33 moodle
docker compose build
docker compose up -d
# instalar (paso 5 del README) y pegar config-extra.php en moodle/config.php (paso 6)
```

Entra a **https://localhost**. Caddy usa un certificado local, así que el navegador
marcará "no seguro" la primera vez → aceptar. Es normal en local.

> En local puedes **omitir** la línea `$CFG->sslproxy = true;` de `config-extra.php`
> si accedes por `http`. Si usas `https://localhost`, déjala.

---

## Qué va en git y qué NO

**SÍ se versiona (código):**
- Este repo de infraestructura (Dockerfile, docker-compose.yml, Caddyfile, README, etc.)
- El **tema** custom de CPI → su propio repo, instalado en `moodle/public/theme/cpi`
- Los **plugins** custom → cada uno su propio repo, en `moodle/public/local/...`, etc.

**NO se versiona:**
- `.env` (secretos)
- `moodledata/` (archivos subidos)
- La base de datos
- **El contenido de los cursos** (cursos, lecciones, exámenes, alumnos, calificaciones)
  → eso vive en la BD; se mueve con respaldo/restauración `.mbz`, no con git.
- El core de Moodle (`moodle/`) → es su propio repo; se actualiza contra upstream.

(El `.gitignore` ya cubre todo esto.)

---

## Ciclo de trabajo local → git → servidor

1. **Local:** editas tema / plugin / config → pruebas en `https://localhost`.
2. `git commit` + `git push`.
3. **Servidor (VPS):** `git pull` de infra y de tus plugins/tema.
4. Si un plugin tocó la base de datos:
   `docker compose exec -u www-data php php /var/www/html/admin/cli/upgrade.php --non-interactive`

El **contenido de los cursos** se sube directo en el servidor (o se autoría en local y
se pasa con `.mbz`), una vez que la plataforma esté terminada.
