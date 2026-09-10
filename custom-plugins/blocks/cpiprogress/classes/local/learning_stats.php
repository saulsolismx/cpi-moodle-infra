<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_cpiprogress\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Cálculo de las métricas del bloque "Aprendizaje" (Etapa 2).
 *
 * Métricas:
 *  - progress: promedio del % de completion de los cursos activos del alumno (los cursos
 *    sin completion configurado no cuentan; los que están al 0% sí cuentan).
 *  - courses:  nº de cursos activos del alumno (excluido el curso-sitio).
 *  - average:  media simple de las notas de los QUIZZES presentados, normalizadas sobre
 *              100, juntando los quizzes de TODOS los cursos del alumno (no media de
 *              medias). Solo cuenta lo que el alumno puede ver. Sin ningún quiz
 *              presentado → '-'.
 *
 * @package    block_cpiprogress
 */
class learning_stats {

    /**
     * Valores por defecto: lo que se muestra si el alumno no tiene nada todavía.
     *
     * 'quizzes' no lo usa el template; es informativo (cuántas notas de quiz entraron en
     * el promedio) para poder auditar la métrica sin recalcularla por separado.
     */
    private const EMPTY_STATS = [
        'progress'     => 0,
        'progresstext' => '0%',
        'courses'      => 0,
        'average'      => '-',
        'quizzes'      => 0,
    ];

    /**
     * Caché por-request, indexada por userid. Evita recalcular si el bloque se pinta
     * más de una vez en la misma petición (varias instancias, o get_content() reentrante).
     *
     * @var array<int, array>
     */
    private static array $cache = [];

    /**
     * Métricas del usuario indicado, calculadas una sola vez por petición.
     *
     * @param int $userid
     * @return array datos listos para el template block_cpiprogress/content
     */
    public static function get(int $userid): array {
        if (array_key_exists($userid, self::$cache)) {
            return self::$cache[$userid];
        }
        return self::$cache[$userid] = self::calculate($userid);
    }

    /** Vacía la caché por-request (útil en tests). */
    public static function reset_cache(): void {
        self::$cache = [];
    }

    /**
     * Cálculo real. Nunca lanza: ante cualquier problema devuelve valores neutros.
     *
     * @param int $userid
     * @return array
     */
    private static function calculate(int $userid): array {
        global $CFG;

        $stats = self::EMPTY_STATS;
        if ($userid <= 0) {
            return $stats;
        }

        require_once($CFG->libdir . '/gradelib.php');

        // onlyactive = true: además de limitar a matrículas activas, enrol_get_users_courses()
        // ya descarta los cursos ocultos que este usuario no puede ver (enrollib.php).
        // Pedimos enablecompletion/showgrades/cacherev para no releer cada curso después.
        $courses = enrol_get_users_courses($userid, true, ['enablecompletion', 'showgrades', 'cacherev']);
        unset($courses[SITEID]);

        if (empty($courses)) {
            return $stats;
        }

        $stats['courses'] = count($courses);

        $progresses = [];
        $grades = [];
        foreach ($courses as $course) {
            $percentage = self::course_progress($course, $userid);
            if ($percentage !== null) {
                $progresses[] = $percentage;
            }
            // Notas de los quizzes presentados en este curso. Se acumulan TODAS en la
            // misma lista para que el promedio sea una media simple global, no una
            // media de medias por curso.
            foreach (self::course_quiz_percentages($course, $userid) as $quizgrade) {
                $grades[] = $quizgrade;
            }
        }

        // Avance global: promedio de los cursos QUE SÍ tienen completion. Si ninguno lo
        // tiene, se queda en 0 (nunca division by zero ni NAN).
        if (!empty($progresses)) {
            $average = array_sum($progresses) / count($progresses);
            $stats['progress'] = (int) round(min(100, max(0, $average)));
        }
        $stats['progresstext'] = $stats['progress'] . '%';

        // Promedio: media simple de todas las notas de quiz presentadas, juntando los
        // quizzes de todos los cursos. Sin ninguno presentado → se queda en '-'.
        $stats['quizzes'] = count($grades);
        if (!empty($grades)) {
            $average = array_sum($grades) / count($grades);
            $stats['average'] = number_format(min(100, max(0, $average)), 1);
        }

        return $stats;
    }

    /**
     * % de completion de un curso para el usuario, o null si el curso no lo tiene
     * configurado / el usuario no es un usuario "rastreado".
     *
     * @param \stdClass $course
     * @param int $userid
     * @return float|null
     */
    private static function course_progress(\stdClass $course, int $userid): ?float {
        try {
            $percentage = \core_completion\progress::get_course_progress_percentage($course, $userid);
        } catch (\Throwable $e) {
            // Un curso con datos inconsistentes no debe tumbar el dashboard.
            debugging('block_cpiprogress: fallo el progreso del curso ' . $course->id .
                ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }

        return $percentage === null ? null : (float) $percentage;
    }

    /**
     * Notas de los quizzes (mod_quiz) PRESENTADOS por el usuario en un curso,
     * normalizadas sobre 100. Devuelve una lista (puede estar vacía).
     *
     * "Presentado" = existe grade_grade con finalgrade no-null para ese grade_item.
     *
     * Privacidad: se excluye una nota si (a) el curso oculta las calificaciones
     * (showgrades = 0), o (b) la nota o su grade_item están ocultos —is_hidden() cubre
     * ambos, incluido "oculto hasta" una fecha— y el usuario no tiene la capacidad
     * moodle/grade:viewhidden en ese curso.
     *
     * @param \stdClass $course
     * @param int $userid
     * @return float[] porcentajes de los quizzes presentados
     */
    private static function course_quiz_percentages(\stdClass $course, int $userid): array {
        // (a) El curso no muestra calificaciones a sus alumnos.
        if (property_exists($course, 'showgrades') && empty($course->showgrades)) {
            return [];
        }

        $percentages = [];

        try {
            // Solo los ítems de calificación que provienen de un mod_quiz.
            $items = \grade_item::fetch_all([
                'courseid'   => $course->id,
                'itemtype'   => 'mod',
                'itemmodule' => 'quiz',
            ]);
            if (empty($items)) {
                return [];
            }

            $context = null;
            foreach ($items as $item) {
                try {
                    $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
                    if (empty($grade) || $grade->finalgrade === null) {
                        // No presentado (o sin calificar todavía): no cuenta.
                        continue;
                    }

                    // (b) Nota o item ocultos: solo cuenta si puede ver notas ocultas.
                    if ($grade->is_hidden()) {
                        $context = $context ?? \context_course::instance($course->id, IGNORE_MISSING);
                        if (!$context || !has_capability('moodle/grade:viewhidden', $context, $userid)) {
                            continue;
                        }
                    }

                    // Normalización igual que el core para el display en porcentaje:
                    // (nota - grademin) / (grademax - grademin) * 100.
                    $grademin = (float) $item->grademin;
                    $grademax = (float) $item->grademax;
                    $range = $grademax - $grademin;
                    if ($range <= 0) {
                        // Quiz sin escala válida (grademax <= grademin): se excluye.
                        continue;
                    }

                    $percentages[] = (((float) $grade->finalgrade - $grademin) / $range) * 100;
                } catch (\Throwable $e) {
                    // Un quiz con datos inconsistentes no debe invalidar el resto.
                    debugging('block_cpiprogress: fallo la nota del quiz (grade_item ' .
                        $item->id . ') en el curso ' . $course->id . ': ' . $e->getMessage(),
                        DEBUG_DEVELOPER);
                }
            }
        } catch (\Throwable $e) {
            debugging('block_cpiprogress: fallo al leer los quizzes del curso ' . $course->id .
                ': ' . $e->getMessage(), DEBUG_DEVELOPER);
            return $percentages;
        }

        return $percentages;
    }
}
