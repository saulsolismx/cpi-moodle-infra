<?php
// Override de idioma local (es) para el core (componente `moodle`).
//
// 'myhome' es la etiqueta del nodo de navegación primaria que apunta a /my/
// (lib/classes/navigation/views/primary.php: get_string('myhome')). En el pack `es`
// vale 'Área personal'; aquí la renombramos a 'Inicio' para que el navbar muestre
// "Inicio" sin duplicar una pestaña extra en custommenuitems.
$string['myhome'] = 'Inicio';
