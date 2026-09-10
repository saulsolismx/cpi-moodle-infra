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

## Botón "Personalizar esta página" del dashboard — solo admin/gestores

Objetivo: que **alumnos y profesores NO** puedan personalizar/romper su dashboard (`/my`),
manteniendo el control para **admin y gestores**. Así todos ven el dashboard uniforme que
diseñamos.

Esto **no** es `mdl_config` sino un **override de permisos** en `mdl_role_capabilities`
(también vive en la BD, tampoco viaja con git, hay que aplicarlo en cada entorno).

### Qué controla el botón

La capacidad **`moodle/my:manageblocks`** (contexto SYSTEM). `public/my/index.php:88` la
fija como capacidad de edición del dashboard; los botones "Personalizar esta página" /
"Restablecer" y el añadir/mover/borrar bloques del `/my` dependen de ella
(`blocks/moodleblock.class.php::user_can_edit` / `user_can_addto`).

Por defecto solo la tiene el rol **7 "Usuario autenticado"** en ALLOW (archetype `user`).
Por eso **todos** los usuarios logueados ven el botón — no viene del rol `student`.

### Por qué Prevent en rol 7 + Allow en rol 1 (y NO en el rol student)

- El rol `student` (5) se asigna a nivel **curso**, que **no** está en la ruta de contexto
  del dashboard (System→User). Un override en `student` **no llega** al dashboard.
- Aggregación de `has_capability` (`lib/accesslib.php::has_capability_in_accessdata`):
  `PROHIBIT` deniega siempre; en otro caso **basta un `ALLOW` en cualquier rol**. Un
  `PREVENT` **no** anula el `ALLOW` de otro rol.
- Por tanto: **Prevent** en el rol 7 (quita el botón a todo no-admin) + **Allow** en el rol
  1 "Gestor" (lo recupera; ALLOW gana). Admin lo conserva por bypass de site admin.
- ⚠️ **Nunca `Prohibit`** en el rol 7: anularía también a gestores y a admins con rol.
- Nota: también se lo quita a **profesores** (dependían del rol 7); es el efecto buscado.

### Comandos (usar la API `assign_capability`, no INSERT crudo)

```bash
docker compose exec -T -u www-data php php /dev/stdin <<'PHP'
<?php
define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
$sys = context_system::instance();
assign_capability('moodle/my:manageblocks', CAP_PREVENT, 7, $sys->id, true); // Usuario autenticado
assign_capability('moodle/my:manageblocks', CAP_ALLOW,   1, $sys->id, true); // Gestor
$sys->mark_dirty();
purge_all_caches();
echo "OK\n";
PHP
```

### Verificación (sin navegador)

```bash
docker compose exec -T -u www-data php php /dev/stdin <<'PHP'
<?php
define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
foreach (['admin'=>2, 'alumno.prueba'=>3] as $l=>$uid) {
  $can = has_capability('moodle/my:manageblocks', context_user::instance($uid), $uid);
  echo "$l: boton ".($can ? 'SÍ' : 'NO')."\n";
}
PHP
```

Esperado: `admin: boton SÍ` · `alumno.prueba: boton NO`. Visualmente: como admin siguen los
botones "Dejar de personalizar esta página" / "Restablecer página a por defecto"; entrando
como el alumno (Perfil → "Entrar como") el dashboard no muestra ningún botón de personalizar
ni el toggle "Modo de edición".

> Efecto colateral práctico: a un alumno que **ya** personalizó su dashboard le desaparecen
> los botones y no puede resetearlo él mismo. Para reunificarlo, el admin lo resetea desde
> la página de dashboard por defecto ("Restablecer Área personal para todos los usuarios").

### Rollback

Estado previo: rol 7 = ALLOW (único), rol 1 sin override.

```bash
docker compose exec -T -u www-data php php /dev/stdin <<'PHP'
<?php
define('CLI_SCRIPT', true);
require('/var/www/html/public/config.php');
$sys = context_system::instance();
assign_capability('moodle/my:manageblocks', CAP_ALLOW, 7, $sys->id, true); // restaura Usuario autenticado
unassign_capability('moodle/my:manageblocks', 1, $sys->id);                 // quita override del Gestor
$sys->mark_dirty();
purge_all_caches();
echo "rollback OK\n";
PHP
```

---

## Historial

| Fecha | Ajuste | Aplicado en |
|---|---|---|
| 2026-09-09 | `custommenuitems` = Perfil + Calendario | local ✅ · prod ✅ |
| 2026-09-09 | override `myhome` = "Inicio" | local ✅ · prod ✅ |
| 2026-09-10 | `my:manageblocks` Prevent rol 7 + Allow rol 1 (dashboard no editable por no-admin) | local ✅ · prod ✅ |

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
