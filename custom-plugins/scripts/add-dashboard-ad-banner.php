<?php
// Anade (o recrea) el BANNER PUBLICITARIO en el DASHBOARD POR DEFECTO del sitio.
//
// Crea un bloque HTML ESTANDAR (block_html) en la columna izquierda (content-left),
// DEBAJO del bloque XP (Aprendizaje) -> peso 3. El bloque es 100% editable por la UI:
//   admin -> Dashboard -> modo edicion -> Configurar bloque -> cambiar imagen/texto/enlace.
//
// La imagen se guarda en el FILEAREA del propio bloque (block_html/content, itemid 0), que
// es exactamente donde la deja el editor de la UI, por lo que aparece en el editor y se puede
// reemplazar sin tocar codigo. En el texto se referencia con @@PLUGINFILE@@/banner.jpg.
//
// Idempotente: si ya existe nuestro banner (marcador CPI_AD_BANNER) en el dashboard por
// defecto, lo borra y lo vuelve a crear limpio.
//
// Uso (dentro del contenedor php, como www-data). La imagen optimizada debe estar accesible
// dentro del contenedor (por defecto /tmp/banner.jpg; se puede pasar otra ruta como argumento):
//   docker cp custom-plugins/scripts/assets/banner.jpg cpi-php-1:/tmp/banner.jpg
//   docker compose exec -T -u www-data php php /dev/stdin [ruta_imagen] < custom-plugins/scripts/add-dashboard-ad-banner.php

define('CLI_SCRIPT', true);

// config.php: dentro del contenedor Moodle vive en /var/www/html/public; en el host, relativo.
$candidates = [
    '/var/www/html/public/config.php',
    dirname(__FILE__) . '/../../moodle/public/config.php',
];
foreach ($candidates as $cfg) {
    if (is_file($cfg)) { require($cfg); break; }
}
require_once($CFG->dirroot . '/my/lib.php');
global $DB, $PAGE;

\core\cron::setup_user(get_admin());

$imagepath = isset($argv[1]) ? $argv[1] : '/tmp/banner.jpg';
if (!is_file($imagepath)) {
    cli_error("ERROR: no encuentro la imagen en '$imagepath'. Copiala al contenedor primero.");
}
$imagename = 'banner.jpg';

$MARKER = 'CPI_AD_BANNER'; // marcador para identificar/idempotencia

// Enlace de WhatsApp de CPI con mensaje predefinido.
$wa = 'https://wa.me/523320200085?text=Hola,%20me%20interesa%20anunciar%20en%20CPI%20Virtual';

// Contenido HTML del bloque. La imagen se referencia via @@PLUGINFILE@@ (filearea del bloque).
$html = <<<HTML
<!-- {$MARKER} -->
<a href="{$wa}" target="_blank" rel="noopener">
  <img src="@@PLUGINFILE@@/{$imagename}" alt="Espacio publicitario - Anúnciate con CPI" style="width:100%; border-radius:8px;">
</a>
<p style="text-align:center; margin-top:8px;">
  <a href="{$wa}" target="_blank" rel="noopener">¿Quieres anunciar tu marca aquí? Contáctanos</a>
</p>
HTML;

// 1) pagina del dashboard por defecto del sitio.
$page = $DB->get_record('my_pages', ['userid' => null, 'private' => MY_PAGE_PRIVATE, 'name' => '__default'], '*', MUST_EXIST);
$subpage = (string)$page->id;
$syscontext = context_system::instance();
echo "Dashboard por defecto: my_pages id={$page->id}\n";

// 2) idempotencia: borrar cualquier banner nuestro previo en el dashboard por defecto.
$existing = $DB->get_records_select('block_instances',
    "parentcontextid = ? AND pagetypepattern = 'my-index' AND subpagepattern = ? AND blockname = 'html'",
    [$syscontext->id, $subpage]);
foreach ($existing as $b) {
    $cfg = $b->configdata ? unserialize(base64_decode($b->configdata)) : null;
    if ($cfg && isset($cfg->text) && strpos($cfg->text, $MARKER) !== false) {
        blocks_delete_instance($b);
        echo "  - Borrado banner previo (block_instances id={$b->id}).\n";
    }
}

// 3) crear el bloque HTML en content-left, peso 3 (debajo de xp que esta en w2).
$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_pagetype('my-index');
$PAGE->set_subpage($subpage);
$PAGE->blocks->add_regions(['content-left', 'content', 'content-right'], false);
$PAGE->blocks->set_default_region('content-left');
$PAGE->blocks->add_block('html', 'content-left', 3, false, 'my-index', $subpage);

// localizar la instancia recien creada (la mas nueva html en este subpage sin nuestro marcador aun).
$instances = $DB->get_records_select('block_instances',
    "parentcontextid = ? AND pagetypepattern = 'my-index' AND subpagepattern = ? AND blockname = 'html'",
    [$syscontext->id, $subpage], 'id DESC');
$bi = reset($instances);
if (!$bi) { cli_error("ERROR: no se pudo crear/localizar el bloque html."); }
echo "  + Bloque html creado: block_instances id={$bi->id} (content-left, w3)\n";

// 4) contexto del bloque (se crea si no existe) y guardar la imagen en su filearea.
$blockcontext = context_block::instance($bi->id);
$fs = get_file_storage();
$filerecord = [
    'contextid' => $blockcontext->id,
    'component' => 'block_html',
    'filearea'  => 'content',
    'itemid'    => 0,
    'filepath'  => '/',
    'filename'  => $imagename,
];
if ($old = $fs->get_file($blockcontext->id, 'block_html', 'content', 0, '/', $imagename)) {
    $old->delete();
}
$fs->create_file_from_pathname($filerecord, $imagepath);
echo "  + Imagen guardada en filearea block_html/content (contextid={$blockcontext->id}, {$imagename})\n";

// 5) configurar titulo y texto del bloque.
$config = new stdClass();
$config->title  = 'Publicidad';
$config->format = FORMAT_HTML;
$config->text   = $html;
$DB->set_field('block_instances', 'configdata', base64_encode(serialize($config)), ['id' => $bi->id]);
echo "  + Configuracion guardada (titulo 'Publicidad', formato HTML).\n";

purge_all_caches();
echo "LISTO. Banner publicitario anadido al dashboard por defecto (content-left, debajo de XP).\n";
echo "Editable por UI: Dashboard -> modo edicion -> Configurar este bloque.\n";
