# Overrides de idioma — CPI Virtual

Personalizaciones de cadenas de idioma (`tool_customlang`). **NO van en el tema**: los
strings de componentes core/plugin (p. ej. `mod_quiz`) solo se overridean desde
`moodledata`. Este directorio versiona una copia reproducible de cada override.

## Despliegue

Cada archivo `lang-overrides/es_local/<componente>.php` se copia a
`moodledata/lang/es_local/<componente>.php` **en cada entorno** (local y prod), como
`www-data` (uid 33), y luego se purgan cachés:

```bash
# dentro del contenedor php, ruta destino:
#   /var/www/moodledata/lang/es_local/<componente>.php
docker compose exec -u www-data php php /var/www/html/admin/cli/purge_caches.php
```

## Overrides

| Archivo | Componente | Claves | Textos |
|---|---|---|---|
| `es_local/quiz.php` | `mod_quiz` | `attemptquiz`, `reattemptquiz`, `continueattemptquiz` | Realizar / Reintentar / Continuar **Evaluación** (botón de intento en todos los cuestionarios) |
| `es_local/moodle.php` | `moodle` (core) | `myhome` | **Inicio** — renombra la pestaña del navbar que apunta a `/my/` (por defecto "Área personal"). Va de la mano de `custommenuitems`; ver [CONFIG-SITIO.md](../CONFIG-SITIO.md) |
