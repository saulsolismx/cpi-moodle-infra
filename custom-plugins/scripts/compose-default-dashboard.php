<?php
// Recompone el DASHBOARD POR DEFECTO del sitio (my_pages userid NULL, private=1) con la
// distribución de 3 columnas de CPI. Reproducible/idempotente: borra los bloques actuales
// del dashboard por defecto y los recrea en el orden correcto.
//
// Distribución:
//   IZQUIERDA (content-left): myprofile (w0) -> cpiprogress (w1) -> xp (w2)
//   CENTRO    (content):      myoverview (w0)
//   DERECHA   (content-right):recentlyaccesseditems (w0) -> timeline (w1)
//
// Requisitos previos: el bloque block_cpiprogress debe estar INSTALADO (copiar a
// moodle/public/blocks/cpiprogress + upgrade.php) y el tema cpi con el layout mydashboard.
//
// Uso (dentro del contenedor php, como www-data):
//   docker compose exec -u www-data php php /ruta/compose-default-dashboard.php

define('CLI_SCRIPT', true);
require(dirname(__FILE__) . '/../../moodle/public/config.php'); // ajusta si la ruta difiere
require_once($CFG->dirroot . '/my/lib.php');
global $DB, $PAGE;

\core\cron::setup_user(get_admin());

// 0) el bloque cpiprogress debe existir.
if (!$DB->record_exists('block', ['name' => 'cpiprogress'])) {
    cli_error("ERROR: el bloque 'cpiprogress' no esta instalado. Instalalo antes (copiar a blocks/ + upgrade.php).");
}

// 1) pagina del dashboard por defecto del sitio.
$page = $DB->get_record('my_pages', ['userid' => null, 'private' => MY_PAGE_PRIVATE, 'name' => '__default'], '*', MUST_EXIST);
$subpage = (string)$page->id;
$syscontext = context_system::instance();
echo "Dashboard por defecto: my_pages id={$page->id}\n";

// 2) borrar bloques actuales del dashboard por defecto (limpio y reproducible).
$existing = $DB->get_records_select('block_instances',
    "parentcontextid = ? AND pagetypepattern = 'my-index' AND subpagepattern = ?",
    [$syscontext->id, $subpage]);
foreach ($existing as $b) {
    blocks_delete_instance($b);
}
echo "Borrados " . count($existing) . " bloques previos del dashboard por defecto.\n";

// 3) recrear la composicion.
$PAGE->set_context($syscontext);
$PAGE->set_pagelayout('mydashboard');
$PAGE->set_pagetype('my-index');
$PAGE->set_subpage($subpage);
$PAGE->blocks->add_regions(['content-left', 'content', 'content-right'], false);
$PAGE->blocks->set_default_region('content-left');

$blocks = [
    ['myprofile',             'content-left',  0],
    ['cpiprogress',           'content-left',  1],
    ['xp',                    'content-left',  2],
    ['myoverview',            'content',       0],
    ['recentlyaccesseditems', 'content-right', 0],
    ['timeline',              'content-right', 1],
];
foreach ($blocks as [$name, $region, $weight]) {
    $PAGE->blocks->add_block($name, $region, $weight, false, 'my-index', $subpage);
    echo "  + $name -> $region (w$weight)\n";
}

purge_all_caches();
echo "LISTO. Dashboard por defecto recompuesto. (Los usuarios con dashboard personalizado deben resetearlo para heredarlo.)\n";
