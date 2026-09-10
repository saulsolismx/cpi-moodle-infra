# Configuración de sitio — CPI Virtual

Ajustes que viven en la tabla `mdl_config` de la base de datos y **NO viajan con git**.
Hay que aplicarlos **en cada entorno** (local y prod) por separado. Este documento los
registra para poder reproducirlos sin adivinar.

Se aplican con `admin/cli/cfg.php` (reproducible y scriptable) en vez de hacer clic en el
admin, para que quede el comando exacto:

```bash
docker compose exec -u www-data php php /var/www/html/admin/cli/cfg.php --name=<ajuste> --set='<valor>'
docker compose exec -u www-data php php /var/www/html/admin/cli/purge_caches.php
```

Para leer el valor actual de un ajuste:

```bash
docker compose exec -u www-data php php /var/www/html/admin/cli/cfg.php --name=<ajuste>
```

---

## Pestañas del navbar (navegación primaria)

Objetivo: que el menú superior muestre **Inicio · Perfil · Calendario**.

Son **dos** cambios que trabajan juntos:

### 1. `custommenuitems` — agrega Perfil y Calendario

Los items del menú personalizado se fusionan en la navegación primaria, al mismo nivel que
los nodos nativos y **después** de ellos
(`lib/classes/navigation/output/primary.php::get_custom_menu`).

Formato: `Etiqueta|URL|Tooltip|Idiomas`, uno por línea; prefijo `-` para submenús.

Valor:

```
Perfil|/user/profile.php
Calendario|/calendar/view.php?view=month
```

Comando:

```bash
docker compose exec -u www-data php php /var/www/html/admin/cli/cfg.php --name=custommenuitems --set='Perfil|/user/profile.php
Calendario|/calendar/view.php?view=month'
docker compose exec -u www-data php php /var/www/html/admin/cli/purge_caches.php
```

> **"Inicio" NO va aquí.** El navbar ya trae un nodo nativo que apunta a `/my/`; añadir un
> "Inicio" propio duplicaría el destino. En su lugar se renombra ese nodo (paso 2).

### 2. Renombrar el nodo nativo `/my/` → "Inicio"

El nodo lo pinta `lib/classes/navigation/views/primary.php` con `get_string('myhome')`, que
en el pack `es` vale "Área personal". Se renombra con un override de idioma del core:
`lang-overrides/es_local/moodle.php` → `$string['myhome'] = 'Inicio';`

Despliegue del override: ver [lang-overrides/README.md](lang-overrides/README.md).

### Estado esperado tras aplicar ambos

El navbar renderiza 3 pestañas (más el contenedor de overflow "Más", oculto hasta que hace
falta):

| Pestaña | URL |
|---|---|
| Inicio | `/my/` |
| Perfil | `/user/profile.php` |
| Calendario | `/calendar/view.php?view=month` |

**No requiere cambios en el tema.** `theme_cpi` no hace override del navbar (hereda el de
boost) y sus reglas `.navbar .nav-link { color: #fff }` ya visten las pestañas en blanco
sobre el navy `#002256`, con hover en `$cpi-grey-200`.

> **Al verificar visualmente:** el navbar usa `core/moremenu`, que por JS colapsa en el
> desplegable "Más" las pestañas que no caben. En ventanas angostas (< ~992 px) puede
> parecer que faltan. Confirmar en el HTML, no solo a ojo.

### Configuración relacionada (no modificada, pero explica lo que se ve)

| Ajuste | Valor | Efecto |
|---|---|---|
| `enablemyhome` | `0` | no se muestra el nodo "Inicio" del sitio (`/`) |
| `enabledashboard` | `1` | sí se muestra el nodo `/my/` (el que renombramos) |
| `enablemycourses` | `0` | no se muestra "Mis cursos" |
| `defaulthomepage` | `1` | la portada del usuario es el Área personal |

---

## Historial

| Fecha | Ajuste | Aplicado en |
|---|---|---|
| 2026-09-09 | `custommenuitems` = Perfil + Calendario | local ✅ · prod ✅ |
| 2026-09-09 | override `myhome` = "Inicio" | local ✅ · prod ✅ |

### Verificación del navbar sin navegador

El navbar no se renderiza en la página de login (layout anónimo), así que para comprobarlo
sin sesión hay que construir la navegación primaria del lado del servidor:

```php
\core\session\manager::set_user($user);
$PAGE->set_context(\context_system::instance());
$PAGE->set_url('/my/');
$PAGE->set_pagelayout('mydashboard');
$data = (new \core\navigation\output\primary($PAGE))->export_for_template($PAGE->get_renderer('core'));
// $data['mobileprimarynav'] = nodos nativos + custom, ya fusionados.
// Ojo: los nodos custom llegan como stdClass y los nativos como array → castear con (array).
```

Resultado esperado en prod para un alumno: `Inicio` → `/my/`, `Perfil` →
`/user/profile.php`, `Calendario` → `/calendar/view.php?view=month`. Un admin ve además
`Administración del sitio`, insertado entre los nativos y los custom (los custom se añaden
**después** de los nativos).

### Rollback

`custommenuitems` estaba **vacío** antes del cambio, en ambos entornos:

```bash
docker compose exec -u www-data php php /var/www/html/admin/cli/cfg.php --name=custommenuitems --set=''
rm /var/www/moodledata/lang/es_local/moodle.php   # dentro del contenedor php
docker compose exec -u www-data php php /var/www/html/admin/cli/purge_caches.php
```
