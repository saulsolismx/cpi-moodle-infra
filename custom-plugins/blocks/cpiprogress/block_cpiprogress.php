<?php
// Bloque "Aprendizaje" (avance global) — CPI Virtual.
//
// ETAPA 1: estructura + maqueta visual con DATOS PLACEHOLDER (sin cálculo real).
// El cálculo real (completion + promedio de notas a través de los cursos) es la Etapa 2.

defined('MOODLE_INTERNAL') || die();

class block_cpiprogress extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_cpiprogress');
    }

    /** Colocable en el dashboard del alumno (y donde sea). */
    public function applicable_formats() {
        return ['all' => true, 'my' => true];
    }

    /** El título va dentro de la maqueta; ocultamos la cabecera por defecto vía CSS. */
    public function hide_header() {
        return true;
    }

    public function get_content() {
        global $OUTPUT;
        if ($this->content !== null) {
            return $this->content;
        }

        // ETAPA 1 — datos FIJOS placeholder (la Etapa 2 los reemplaza por el cálculo real).
        $data = [
            'progress'     => 65,     // % avance global (placeholder).
            'progresstext' => '65%',
            'courses'      => 3,      // nº de cursos (placeholder).
            'average'      => '80.0', // promedio de calificaciones (placeholder).
        ];

        $this->content = new stdClass();
        $this->content->text = $OUTPUT->render_from_template('block_cpiprogress/content', $data);
        $this->content->footer = '';
        return $this->content;
    }
}
