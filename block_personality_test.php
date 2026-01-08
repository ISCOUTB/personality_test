<?php
/**
 * Personality Test Block
 *
 * @package    block_personality_test
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

// Fachada para la lógica de negocio del test de personalidad
class PersonalityTestFacade {
    private static $mbti_types = [
        "ISTJ", "ISFJ", "INFJ", "INTJ", "ISTP", "ISFP", "INFP", "INTP",
        "ESTP", "ESFP", "ENFP", "ENTP", "ESTJ", "ESFJ", "ENFJ", "ENTJ"
    ];

    public static function get_mbti_type($entry) {
        $mbti = '';
        $mbti .= ($entry->extraversion > $entry->introversion) ? 'E' : 'I';
        $mbti .= ($entry->sensing > $entry->intuition) ? 'S' : 'N';
        $mbti .= ($entry->thinking >= $entry->feeling) ? 'T' : 'F';
        $mbti .= ($entry->judging > $entry->perceptive) ? 'J' : 'P';
        return $mbti;
    }

    public static function get_mbti_counts($students) {
        $mbti_count = array_fill_keys(self::$mbti_types, 0);
        foreach ($students as $entry) {
            if (!isset($entry->extraversion, $entry->introversion, $entry->sensing, $entry->intuition, $entry->thinking, $entry->feeling, $entry->judging, $entry->perceptive)) {
                continue;
            }
            $mbti = self::get_mbti_type($entry);
            if (isset($mbti_count[$mbti])) {
                $mbti_count[$mbti]++;
            }
        }
        return $mbti_count;
    }

    public static function get_aspect_counts($students) {
        $aspect_counts = [
            "Introvertido" => 0, "Extrovertido" => 0,
            "Sensing" => 0, "Intuición" => 0,
            "Pensamiento" => 0, "Sentimiento" => 0,
            "Juicio" => 0, "Percepción" => 0
        ];
        foreach ($students as $entry) {
            if (!isset($entry->extraversion, $entry->introversion, $entry->sensing, $entry->intuition, $entry->thinking, $entry->feeling, $entry->judging, $entry->perceptive)) {
                continue;
            }
            $aspect_counts["Introvertido"] += ($entry->introversion >= $entry->extraversion) ? 1 : 0;
            $aspect_counts["Extrovertido"] += ($entry->extraversion > $entry->introversion) ? 1 : 0;
            $aspect_counts["Sensing"] += ($entry->sensing > $entry->intuition) ? 1 : 0;
            $aspect_counts["Intuición"] += ($entry->intuition >= $entry->sensing) ? 1 : 0;
            $aspect_counts["Pensamiento"] += ($entry->thinking >= $entry->feeling) ? 1 : 0;
            $aspect_counts["Sentimiento"] += ($entry->feeling > $entry->thinking) ? 1 : 0;
            $aspect_counts["Juicio"] += ($entry->judging > $entry->perceptive) ? 1 : 0;
            $aspect_counts["Percepción"] += ($entry->perceptive >= $entry->judging) ? 1 : 0;
        }
        return $aspect_counts;
    }
}

class block_personality_test extends block_base
{
    function init()
    {
        $this->title = get_string('pluginname', 'block_personality_test');
    }

    function instance_allow_multiple()
    {
        return false;
    }

    /**
     * Genera el contenido para la vista del profesor.
     *
     * @param object $DB La instancia global de la base de datos de Moodle.
     * @param object $COURSE El objeto global del curso actual.
     * @return stdClass Un objeto con las propiedades 'text' y 'footer' para el contenido del bloque.
     */
    private function _get_teacher_content($DB, $COURSE) {
        global $OUTPUT;

        $content = new stdClass();
        $content->text = '';
        $content->footer = '';

        // Obtener usuarios inscritos que pueden realizar el test (por defecto: estudiantes)
        $context = context_course::instance($COURSE->id);
        $enrolled_students = get_enrolled_users($context, 'block/personality_test:taketest', 0, 'u.id', null, 0, 0, true);
        $student_ids = array_keys($enrolled_students);

        // Defensive: exclude any teacher/manager-type user even if misconfigured.
        $filtered_student_ids = array();
        foreach ($student_ids as $candidateid) {
            $candidateid = (int)$candidateid;
            if (is_siteadmin($candidateid)) {
                continue;
            }
            if (has_capability('block/personality_test:viewreports', $context, $candidateid)) {
                continue;
            }
            $filtered_student_ids[] = $candidateid;
        }
        $student_ids = $filtered_student_ids;
        $total_students = count($student_ids);
        
        // Obtener respuestas solo de estudiantes inscritos que hayan COMPLETADO el test
        $students = array();
        if (!empty($student_ids)) {
            list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'user');
            $params['completed'] = 1;
            // OPTIMIZATION: Only fetch necessary columns, avoid fetching all 72 question columns
            $sql = "SELECT id, user, extraversion, introversion, sensing, intuition, thinking, feeling, judging, perceptive 
                    FROM {personality_test} WHERE user $insql AND is_completed = :completed";
            $students = $DB->get_records_sql($sql, $params);
        }

        $has_data = !empty($students);
        $in_progress = 0;

        // Si no hay tests completados, mostrar estadísticas básicas
        if(!$has_data) {
            // Contar tests en progreso
            if (!empty($student_ids)) {
                list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'user');
                $params['completed'] = 0;
                $in_progress = $DB->count_records_select('personality_test', "user $insql AND is_completed = :completed", $params);
            }
        }

        $template_data = [
            'icon' => $this->get_personality_test_icon('4em', '', true),
            'has_data' => $has_data,
            'total_students' => $total_students,
            'in_progress' => $in_progress,
        ];

        if ($has_data) {
            $mbti_count = PersonalityTestFacade::get_mbti_counts($students);
            $aspect_counts = PersonalityTestFacade::get_aspect_counts($students);

            // Preparar datos para JavaScript
            $header_strings = array(
                'titulo_distribucion_mbti' => get_string('titulo_distribucion_mbti', 'block_personality_test'),
                'num_estudiantes_header' => get_string('num_estudiantes_header', 'block_personality_test'),
                'introversion_extroversion' => get_string('introversion_extroversion', 'block_personality_test'),
                'sensacion_intuicion' => get_string('sensacion_intuicion', 'block_personality_test'),
                'pensamiento_sentimiento' => get_string('pensamiento_sentimiento', 'block_personality_test'),
                'juicio_percepcion' => get_string('juicio_percepcion', 'block_personality_test'),
                'Introvertido' => get_string('Introvertido', 'block_personality_test'),
                'Extrovertido' => get_string('Extrovertido', 'block_personality_test'),
                'Sensing' => get_string('Sensing', 'block_personality_test'),
                'Intuicion' => get_string('Intuicion', 'block_personality_test'),
                'Pensamiento' => get_string('Pensamiento', 'block_personality_test'),
                'Sentimiento' => get_string('Sentimiento', 'block_personality_test'),
                'Juicio' => get_string('Juicio', 'block_personality_test'),
                'Percepcion' => get_string('Percepcion', 'block_personality_test'),
                'sin_datos_estudiantes' => get_string('sin_datos_estudiantes', 'block_personality_test')
            );

            // Mostrar estadísticas de participación
            $total_completed = count($students);
            $completion_percentage = $total_students > 0 ? round(($total_completed / $total_students) * 100, 1) : 0;
            
            $participation_data = new stdClass();
            $participation_data->completed = $total_completed;
            $participation_data->total = $total_students;
            $participation_data->percentage = $completion_percentage;
            
            $template_data['completed_str'] = get_string('students_completed_test', 'block_personality_test', $participation_data);
            $template_data['csv_url'] = (new moodle_url('/blocks/personality_test/download_csv.php', ['courseid' => $COURSE->id, 'sesskey' => sesskey()]))->out(false);
            $template_data['pdf_url'] = (new moodle_url('/blocks/personality_test/download_pdf.php', ['courseid' => $COURSE->id, 'sesskey' => sesskey()]))->out(false);

            // Call AMD module
            $this->page->requires->js_call_amd('block_personality_test/charts', 'init', [
                array_filter($mbti_count),
                $aspect_counts,
                $header_strings
            ]);
        }
        
        $content->text = $OUTPUT->render_from_template('block_personality_test/teacher_dashboard', $template_data);
        return $content;
    }

    /**
     * Helper method to generate personality test icon HTML
     * @param string $size Icon size (default: 1.8em)
     * @param string $additional_style Additional inline styles
     * @param bool $centered Whether to center the icon
     * @return string HTML img tag with the SVG icon
     */
    private function get_personality_test_icon($size = '1.8em', $additional_style = '', $centered = false) {
        $iconurl = new moodle_url('/blocks/personality_test/pix/icon.svg');
        $style = 'width: ' . $size . '; height: ' . $size . '; vertical-align: middle; float: none !important;';
        if ($centered) {
            $style .= ' display: block; margin: 0 auto;';
        }
        if (!empty($additional_style)) {
            $style .= ' ' . $additional_style;
        }
        return '<img class="personality-test-icon" src="' . $iconurl . '" alt="Personality Test Icon" style="' . $style . '" />';
    }

    /**
     * Método para mostrar la invitación al test de personalidad
     */
    private function get_test_invitation() {
        global $COURSE, $OUTPUT;
        
        $url = new moodle_url('/blocks/personality_test/view.php', array('cid' => $COURSE->id));
        $data = [
            'icon' => $this->get_personality_test_icon('4em', '', true),
            'url' => $url->out(false)
        ];
        
        return $OUTPUT->render_from_template('block_personality_test/student_invitation', $data);
    }

    /**
     * Display continue test card for students with test in progress
     */
    private function get_continue_test_card($answered_count) {
        global $COURSE, $OUTPUT;
        
        $progress_percentage = ($answered_count / 72) * 100;
        $all_answered = ($answered_count >= 72);
        
        $url_params = ['cid' => $COURSE->id];
        if ($all_answered) {
            $url_params['page'] = 8;
            $url_params['scroll_to_finish'] = 1;
        }
        $url = new moodle_url('/blocks/personality_test/view.php', $url_params);

        $data = [
            'icon' => $this->get_personality_test_icon('4em', 'text-shadow: 0 1px 2px rgba(0,0,0,0.1);', true),
            'answered_count' => $answered_count,
            'progress_percentage' => $progress_percentage,
            'progress_percentage_formatted' => number_format($progress_percentage, 1),
            'all_answered' => $all_answered,
            'url' => $url->out(false)
        ];
        
        return $OUTPUT->render_from_template('block_personality_test/student_continue', $data);
    }
    
    function get_content()
    {
        // Declarar globales necesarios
        global $OUTPUT, $CFG, $DB, $USER, $COURSE, $SESSION;

        // --- Validaciones Iniciales ---
        // No mostrar en la página principal del sitio
        if ($COURSE->id == SITEID) {
             $this->content = new stdClass; $this->content->text = ''; $this->content->footer = '';
            return $this->content;
        }

        // Si el contenido ya se generó, devolverlo
        if ($this->content !== NULL) {
            return $this->content;
        }

        // Inicializar objeto de contenido
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($this->instance)) {
            return $this->content;
        }

        if (!isloggedin()) {
             return $this->content;
        }

        // --- Lógica Principal ---

        $context = context_course::instance($COURSE->id);

        // Si el usuario tiene permiso de ver reportes, mostrar la vista del profesor/administración.
        if (has_capability('block/personality_test:viewreports', $context)) {
            $teacher_content = $this->_get_teacher_content($DB, $COURSE);
            $this->content = $teacher_content;
            
            // Agregar enlace a la vista administrativa
            $admin_url = new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $COURSE->id));
            $this->content->footer .= html_writer::div(
                html_writer::link($admin_url,
                    get_string('go_to_administration', 'block_personality_test'),
                    array('class' => 'btn btn-sm mt-2', 'style' => 'background: linear-gradient(135deg, #00bf91 0%, #00a07a 100%); color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none; border: none;')
                ),
                'text-center'
            );
            return $this->content;
        }

        // Si el usuario puede realizar el test, mostrar la vista del estudiante.
        if (has_capability('block/personality_test:taketest', $context)) {
            // Verificar si el estudiante ya tiene una entrada en la tabla
            $entry = $DB->get_record('personality_test', array('user' => $USER->id));

            if (!$entry) {
                // El estudiante NO ha realizado el test todavía - Mostrar invitación
                $this->content->text = $this->get_test_invitation();
            } else if ($entry && isset($entry->is_completed) && $entry->is_completed == 0) {
                // Test in progress - show continue option
                $answered = 0;
                for ($i = 1; $i <= 72; $i++) {
                    $field = "q{$i}";
                    if (isset($entry->$field) && $entry->$field !== null) {
                        $answered++;
                    }
                }
                $this->content->text = $this->get_continue_test_card($answered);
            } else {
                // El estudiante YA HA realizado el test - Mostrar sus resultados

                 if (!isset($entry->extraversion, $entry->introversion, $entry->sensing, $entry->intuition, $entry->thinking, $entry->feeling, $entry->judging, $entry->perceptive)) {
                     $this->content->text = get_string('error_recuperando_resultados', 'block_personality_test');
                 } else {
                    $scores = array(
                        "extraversion" => $entry->extraversion, "introversion" => $entry->introversion,
                        "sensing" => $entry->sensing, "intuition" => $entry->intuition,
                        "thinking" => $entry->thinking, "feeling" => $entry->feeling,
                        "judging" => $entry->judging, "perceptive" => $entry->perceptive
                    );

                    $mbti_score = "";
                    $mbti_score .= ($scores["extraversion"] >= $scores["introversion"]) ? "E" : "I";
                    $mbti_score .= ($scores["sensing"] > $scores["intuition"]) ? "S" : "N";
                    $mbti_score .= ($scores["thinking"] >= $scores["feeling"]) ? "T" : "F";
                    $mbti_score .= ($scores["judging"] > $scores["perceptive"]) ? "J" : "P";

                    $mbti_labels = [
                        get_string('introversion', 'block_personality_test'),
                        get_string('feeling', 'block_personality_test'),
                        get_string('sensing', 'block_personality_test'),
                        get_string('perceptive', 'block_personality_test'),
                        get_string('extraversion', 'block_personality_test'),
                        get_string('thinking', 'block_personality_test'),
                        get_string('intuition', 'block_personality_test'),
                        get_string('judging', 'block_personality_test')
                    ];

                    $user_data = [
                        (float)$scores['introversion'],
                        (float)$scores['feeling'],
                        (float)$scores['sensing'],
                        (float)$scores['perceptive'],
                        (float)$scores['extraversion'],
                        (float)$scores['thinking'],
                        (float)$scores['intuition'],
                        (float)$scores['judging'],
                    ];

                    $chart_id = 'graficoRadar_' . $USER->id;
                    
                    // Call AMD module for Radial Chart
                    $this->page->requires->js_call_amd('block_personality_test/results_radar', 'init', [
                        $chart_id,
                        $mbti_labels,
                        $user_data,
                        get_string('mbti_type', 'block_personality_test') . ': ' . $mbti_score
                    ]);

                    $template_data = [
                        'icon' => $this->get_personality_test_icon('4em', 'display: block;', false),
                        'mbti_score' => $mbti_score,
                        'mbti_dimensions_str' => get_string('mbti_dimensions_' . strtolower($mbti_score), 'block_personality_test'),
                        'mbti_description' => get_string('mbti_' . strtolower($mbti_score), 'block_personality_test'),
                        'chart_id' => $chart_id,
                        'uniqid' => $USER->id,
                        'instanceid' => $this->instance->id
                    ];
                    
                    $this->content->text = $OUTPUT->render_from_template('block_personality_test/student_results', $template_data);
                    
                    return $this->content;
                 }
            }
        }

        return $this->content;
    } 
}
