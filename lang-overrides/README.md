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

| Archivo | Componente | Claves | Textos | Estado |
|---|---|---|---|---|
| `es_local/quiz.php` | `mod_quiz` | `attemptquiz`, `reattemptquiz`, `continueattemptquiz` | Realizar / Reintentar / Continuar **Evaluación** (botón de intento en todos los cuestionarios) | local ❌ · prod ✅ |
| `es_local/moodle.php` | `moodle` (core) | `myhome` | **Inicio** — renombra la pestaña del navbar que apunta a `/my/` (por defecto "Área personal"). Va de la mano de `custommenuitems`; ver [CONFIG-SITIO.md](../CONFIG-SITIO.md) | local ✅ · prod ✅ |

> **`quiz.php` no está instalado en local** (sí en prod, desde 2026-09-09). Consecuencia: en
> la Mac el botón dice "Realizar cuestionario" y en prod "Realizar Evaluación". Para igualar
> local, instalarlo con el procedimiento de arriba.

## Verificación

El override se comprueba resolviendo el string, sin depender de mirar la interfaz:

```bash
# dentro del contenedor php, como www-data, en un script CLI con config.php cargado:
#   get_string('attemptquiz', 'quiz')  -> 'Realizar Evaluación'
#   get_string('myhome')               -> 'Inicio'
```

Si devuelve el texto original, falta purgar cachés o el archivo no está en
`moodledata/lang/es_local/` con ownership `www-data` (uid 33).
