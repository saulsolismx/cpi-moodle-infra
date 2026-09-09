# Plugins custom y composición del dashboard — CPI Virtual

Código propio (no third-party) que NO vive en el repo del tema ni bajo `moodle/`
(gitignored). Se versiona aquí para reproducibilidad entre máquinas/entornos.

## 1. Bloque `block_cpiprogress` ("Aprendizaje" / avance global)

- **Fuente versionada:** `custom-plugins/blocks/cpiprogress/`
- **Estado:** Etapa 1 — estructura + maqueta con datos PLACEHOLDER (65% / 3 / 80.0). El
  cálculo real (completion + promedio de notas) es la Etapa 2 (pendiente).

### Instalar en un entorno (local o prod)
```bash
# desde la raíz del repo de infra:
cp -r custom-plugins/blocks/cpiprogress moodle/public/blocks/cpiprogress
sudo chown -R 33:33 moodle/public/blocks/cpiprogress      # www-data
docker compose exec -u www-data php php /var/www/html/admin/cli/upgrade.php --non-interactive
docker compose exec -u www-data php php /var/www/html/admin/cli/purge_caches.php
```

## 2. Composición del dashboard por defecto (config en BD, NO git)

La distribución de bloques del dashboard por defecto (3 columnas) vive en la BD, no en
git. Se recrea con el script reproducible:

- **Script:** `custom-plugins/scripts/compose-default-dashboard.php`
- **Distribución:** izquierda = Perfil → Aprendizaje(cpiprogress) → Level Up XP;
  centro = Mis cursos; derecha = Recientes → Línea de tiempo.

### Ejecutar (tras instalar el bloque y tener el tema cpi con el layout mydashboard)
```bash
docker cp custom-plugins/scripts/compose-default-dashboard.php cpi-php-1:/tmp/compose.php
docker compose exec -u www-data php php /tmp/compose.php
```
Ajusta la ruta a `config.php` dentro del script si tu layout de carpetas difiere.
Nota: los usuarios que YA tienen dashboard personalizado deben resetearlo
("Restablecer página" en /my, o `my_reset_page`) para heredar el nuevo default.

## 3. Dependencias (van por sus propios repos)
- **Tema `cpi`** (repo `cpi-theme`, rama `test` mientras el dashboard está en desarrollo):
  layout de 3 columnas `mydashboard` + SCSS. Se despliega con git pull del tema.
- Plugins de terceros: ver `PLUGINS.md`.
