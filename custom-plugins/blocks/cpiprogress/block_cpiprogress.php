<?php
// Bloque "Aprendizaje" (avance global) — CPI Virtual.
//
// ETAPA 2: cálculo real. Las métricas (avance global por completion, nº de cursos y
// promedio de notas) las calcula \block_cpiprogress\local\learning_stats, con caché
// por-request. El bloque solo muestra datos del usuario en sesión.

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
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        // Solo tiene sentido para un usuario real: el bloque siempre habla de SUS datos,
        // nunca de los de un tercero. Invitado o sesión anónima → bloque vacío.
        if (!isloggedin() || isguestuser()) {
            $this->content->text = '';
            return $this->content;
        }

        $data = \block_cpiprogress\local\learning_stats::get((int) $USER->id);
        $this->content->text = $OUTPUT->render_from_template('block_cpiprogress/content', $data);

        return $this->content;
    }
}
