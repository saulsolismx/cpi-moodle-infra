<?php
// =============================================================================
// LINEAS A AGREGAR en moodle/config.php DESPUES de la instalacion.
// Pegalas JUSTO ANTES de la linea:  require_once(__DIR__ . '/lib/setup.php');
// NO copies la etiqueta <?php de arriba (config.php ya la tiene).
// =============================================================================

// Caddy termina el TLS al frente: Moodle debe asumir HTTPS.
$CFG->sslproxy = true;

// Sesiones en Redis (servicio "redis" del compose).
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = 'redis';
$CFG->session_redis_port = 6379;
$CFG->session_redis_prefix = 'mdl_sess_';
