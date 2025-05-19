<?php

require_once(__DIR__ . '/../../config.php');

// Fachada para la lógica de negocio del test de personalidad
class PersonalityTestFacade {
    private static $mbti_types = [
        "ISTJ", "ISFJ", "INFJ", "INTJ", "ISTP", "ISFP", "INFP", "INTP",
        "ESTP", "ESFP", "ENFP", "ENTP", "ESTJ", "ESFJ", "ENFJ", "ENTJ"
    ];

    private static $mbti_explanations = [
        "ISTJ" => "práctica y centrada en los hechos, cuya fiabilidad no puede ser cuestionada.",
        "ISFJ" => "protectora muy dedicada y cálida, siempre lista para defender a sus seres queridos.",
        "INFJ" => "tranquila y mística, pero muy inspiradora e incansable idealista.",
        "INTJ" => "visionaria, pensadora estratégica y resolvente de problemas lógicos.",
        "ISTP" => "experimentadora audaz y práctica, maestra de todo tipo de herramientas.",
        "ISFP" => "artistica flexible y encantadora, siempre dispuesta a explorar y experimentar algo nuevo.",
        "INFP" => "poética, amable y altruista, siempre dispuesta por ayudar a una buena causa.",
        "INTP" => "creativa e innovadora con una sed insaciable de conocimiento.",
        "ESTP" => "inteligente, enérgica y muy perceptiva, que realmente disfruta viviendo al límite.",
        "ESFP" => "espontánea, enérgica y entusiasta.",
        "ENFP" => "de espíritu libre, entusiasta, creativa y sociable, que siempre pueden encontrar una razón para sonreír.",
        "ENTP" => "pensadora, inteligente y curiosa, que no puede resistirse a un desafío intelectual.",
        "ESTJ" => "práctica y centrada en los hechos, cuya fiabilidad no puede ser cuestionada.",
        "ESFJ" => "extraordinariamente cariñosa, sociable y popular, siempre dispuesta a ayudar.",
        "ENFJ" => "líder, carismática e inspiradora, capaz de cautivar a su audiencia.",
        "ENTJ" => "líder, audaz, imaginativa y de voluntad fuerte, siempre encontrando una forma, o creándola."
    ];

    public static function get_mbti_type($entry) {
        $mbti = '';
        $mbti .= ($entry->extraversion >= $entry->introversion) ? 'E' : 'I';
        $mbti .= ($entry->sensing > $entry->intuition) ? 'S' : 'N';
        $mbti .= ($entry->thinking >= $entry->feeling) ? 'T' : 'F';
        $mbti .= ($entry->judging > $entry->perceptive) ? 'J' : 'P';
        return $mbti;
    }

    public static function get_mbti_explanation($mbti) {
        return self::$mbti_explanations[$mbti] ?? '';
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
            $aspect_counts["Introvertido"] += ($entry->introversion > $entry->extraversion) ? 1 : 0;
            $aspect_counts["Extrovertido"] += ($entry->extraversion >= $entry->introversion) ? 1 : 0;
            $aspect_counts["Sensing"] += ($entry->sensing > $entry->intuition) ? 1 : 0;
            $aspect_counts["Intuición"] += ($entry->intuition >= $entry->sensing) ? 1 : 0;
            $aspect_counts["Pensamiento"] += ($entry->thinking > $entry->feeling) ? 1 : 0;
            $aspect_counts["Sentimiento"] += ($entry->feeling >= $entry->thinking) ? 1 : 0;
            $aspect_counts["Juicio"] += ($entry->judging > $entry->perceptive) ? 1 : 0;
            $aspect_counts["Percepción"] += ($entry->perceptive >= $entry->judging) ? 1 : 0;
        }
        return $aspect_counts;
    }
}

class block_personality_test extends block_base
{
    // Función auxiliar no usada directamente en get_content pero puede ser útil
    function my_slider($name, $min, $max, $value, $izq_val, $der_val)
    {
        $slider = '';
        $slider .= '<div class="slider-container" style="text-align:center">';
        $slider .= $izq_val  . " ↹ " .  $der_val . "<br>";
        $slider .= '<input type="range" class="alpy" name="' . $name . '" min="' . $min . '" max="' . $max . '" value="' . $value . '" disabled>';
        $slider .= '</div>';
        return $slider;
    }

    function init()
    {
        $this->title = get_string('pluginname', 'block_personality_test');
    }

    /*function has_config() {
        return false;
    }*/

    function instance_allow_multiple()
    {
        return false;
    }

    
    // Función para la vista del Profesor 
    
    /**
     * Genera el contenido para la vista del profesor.
     *
     * @param object $DB La instancia global de la base de datos de Moodle.
     * @param object $COURSE El objeto global del curso actual.
     * @return stdClass Un objeto con las propiedades 'text' y 'footer' para el contenido del bloque.
     */
    private function _get_teacher_content($DB, $COURSE) {
        // Necesitamos $OUTPUT global aquí para moodle_url si se usa directamente o para get_string
        global $OUTPUT;


        // ---- INICIO TEST DEBUG ----
        debugging("TEST get_string: " . get_string('titulo_distribucion_mbti', 'block_personality_test'), DEBUG_DEVELOPER);
        // ---- FIN TEST DEBUG ----
        // Inicializar el objeto de contenido para esta función
        $content = new stdClass();
        $content->text = '';
        $content->footer = '';

        // --- Vista del Profesor ---
        $students = $DB->get_records('personality_test', ['course' => $COURSE->id]);

        // Depuración: Mostrar número de estudiantes encontrados
        debugging('Número de estudiantes encontrados: ' . count($students), DEBUG_DEVELOPER);

        if(empty($students)) {
            $content->text = get_string('sin_datos_estudiantes', 'block_personality_test');
            return $content;
        }

        $mbti_types = ["ISTJ", "ISFJ", "INFJ", "INTJ", "ISTP", "ISFP", "INFP", "INTP", "ESTP", "ESFP", "ENFP", "ENTP", "ESTJ", "ESFJ", "ENFJ", "ENTJ"];
        $mbti_count = array_fill_keys($mbti_types, 0);

        $aspect_counts = [
            "Introvertido" => 0, "Extrovertido" => 0,
            "Sensing" => 0, "Intuición" => 0,
            "Pensamiento" => 0, "Sentimiento" => 0,
            "Juicio" => 0, "Percepción" => 0
        ];

        foreach ($students as $entry) {
            // Depuración: Mostrar datos de cada estudiante
            debugging('Procesando estudiante - ID: ' . $entry->id . 
                     ' E/I: ' . $entry->extraversion . '/' . $entry->introversion .
                     ' S/N: ' . $entry->sensing . '/' . $entry->intuition .
                     ' T/F: ' . $entry->thinking . '/' . $entry->feeling .
                     ' J/P: ' . $entry->judging . '/' . $entry->perceptive, 
                     DEBUG_DEVELOPER);

            if (!isset($entry->extraversion, $entry->introversion, $entry->sensing, $entry->intuition, $entry->thinking, $entry->feeling, $entry->judging, $entry->perceptive)) {
                continue;
            }

            $mbti_score = "";
            $mbti_score .= ($entry->extraversion >= $entry->introversion) ? "E" : "I";
            $mbti_score .= ($entry->sensing > $entry->intuition) ? "S" : "N";
            $mbti_score .= ($entry->thinking >= $entry->feeling) ? "T" : "F";
            $mbti_score .= ($entry->judging > $entry->perceptive) ? "J" : "P";

            if (isset($mbti_count[$mbti_score])) {
                $mbti_count[$mbti_score]++;
            }

            $aspect_counts["Introvertido"] += ($entry->introversion > $entry->extraversion) ? 1 : 0;
            $aspect_counts["Extrovertido"] += ($entry->extraversion >= $entry->introversion) ? 1 : 0;
            $aspect_counts["Sensing"] += ($entry->sensing > $entry->intuition) ? 1 : 0;
            $aspect_counts["Intuición"] += ($entry->intuition >= $entry->sensing) ? 1 : 0;
            $aspect_counts["Pensamiento"] += ($entry->thinking > $entry->feeling) ? 1 : 0;
            $aspect_counts["Sentimiento"] += ($entry->feeling >= $entry->thinking) ? 1 : 0;
            $aspect_counts["Juicio"] += ($entry->judging > $entry->perceptive) ? 1 : 0;
            $aspect_counts["Percepción"] += ($entry->perceptive >= $entry->judging) ? 1 : 0;
        }

        $mbti_json = json_encode(array_filter($mbti_count));
        $aspects_json = json_encode($aspect_counts);

        $str_titulo_distribucion_mbti = json_encode(get_string('titulo_distribucion_mbti', 'block_personality_test'));
        $str_num_estudiantes = json_encode(get_string('num_estudiantes', 'block_personality_test'));
        $str_introversion_extroversion = json_encode(get_string('introversion_extroversion', 'block_personality_test'));
        $str_sensacion_intuicion = json_encode(get_string('sensacion_intuicion', 'block_personality_test'));
        $str_pensamiento_sentimiento = json_encode(get_string('pensamiento_sentimiento', 'block_personality_test'));
        $str_juicio_percepcion = json_encode(get_string('juicio_percepcion', 'block_personality_test'));

        $content->text .= html_writer::tag('h6',get_string('titulo_resultados_estudiantes', 'block_personality_test'),['style' => 'text-align: center;']);
        $content->text .= html_writer::tag('canvas', '', ['id' => 'mbtiChart', 'style' => 'max-width: 100%; height: auto;']);
        $content->text .= html_writer::tag('h6', get_string('titulo_distribucion_rasgos', 'block_personality_test'), ['style' => 'text-align: center; margin-top: 20px;']);

        $content->text .= html_writer::start_div('d-flex flex-wrap justify-content-around');
        $chart_style = "width: 100%; max-width: 350px; margin: 10px; box-sizing: border-box;";
        $content->text .= html_writer::div(html_writer::tag('canvas', '', ['id' => 'generalTrendChart']), '', ['style' => $chart_style]);
        $content->text .= html_writer::div(html_writer::tag('canvas', '', ['id' => 'infoProcessingChart']), '', ['style' => $chart_style]);
        $content->text .= html_writer::div(html_writer::tag('canvas', '', ['id' => 'decisionMakingChart']), '', ['style' => $chart_style]);
        $content->text .= html_writer::div(html_writer::tag('canvas', '', ['id' => 'organizationChart']), '', ['style' => $chart_style]);
        $content->text .= html_writer::end_div();

        // Preparar datos para JavaScript
        $page = $this->page;
        $page->requires->js_call_amd('block_personality_test/charts', 'init', array(
            json_decode($mbti_json),
            $aspect_counts,
            array(
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
            )
        ));

        $csv_url = new moodle_url('/blocks/personality_test/download_csv.php', ['courseid' => $COURSE->id, 'sesskey' => sesskey()]);
        $pdf_url = new moodle_url('/blocks/personality_test/download_pdf.php', ['courseid' => $COURSE->id, 'sesskey' => sesskey()]);

        $download_links = html_writer::start_div('text-center', ['style' => 'margin-top: 20px;']);
        $link_attributes = [
            'class' => 'btn btn-sm btn-success d-inline-block',
            'style' => 'margin: 5px;',
            'role' => 'button'
        ];
        $download_links .= html_writer::link($csv_url, get_string('CSV', 'block_personality_test'), $link_attributes);
        $download_links .= html_writer::link($pdf_url, get_string('PDF', 'block_personality_test'), $link_attributes);
        $download_links .= html_writer::end_div();

        $content->text .= $download_links;
        $content->footer = '';
        return $content;
    }
    
    //  Función para la vista del Profesor
    


    function get_content()
    {
        // Declarar globales necesarios
        global $OUTPUT, $CFG, $DB, $USER, $COURSE, $SESSION;

        // --- Validaciones Iniciales ---
        // No mostrar en la página principal del sitio
        if ($COURSE->id == SITEID) {
             $this->content = new stdClass; $this->content->text = ''; $this->content->footer = ''; // Necesario inicializar para evitar error
            return $this->content; // Devolver objeto vacío en lugar de nada
        }

        // Si el contenido ya se generó, devolverlo
        if ($this->content !== NULL) {
            return $this->content;
        }

        // Inicializar objeto de contenido
        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        // Si la instancia del bloque no está configurada (?)
        if (empty($this->instance)) {
            // Podría mostrar un error o mensaje aquí si fuera necesario
            return $this->content;
        }

        // Si el usuario no está logueado
        if (!isloggedin()) {
            // No se devuelve nada explícitamente, pero el contenido estará vacío.
            // Considera añadir un mensaje como: $this->content->text = get_string('logintoview');
             return $this->content; // Devolver objeto vacío
        }

        // --- Lógica Principal ---

        // Comprobar si el usuario actual es un estudiante en este curso (usando rol ID 5)
        $sql = "SELECT m.id
                FROM {user} m
                LEFT JOIN {role_assignments} m2 ON m.id = m2.userid
                LEFT JOIN {context} m3 ON m2.contextid = m3.id
                LEFT JOIN {course} m4 ON m3.instanceid = m4.id
                WHERE m3.contextlevel = 50 AND m2.roleid = 5 AND m.id = ? AND m4.id = ?";
        $params = [$USER->id, $COURSE->id];
        $COURSE_ROLED_AS_STUDENT = $DB->get_record_sql($sql, $params);

        // Comprobar si la consulta devolvió un ID (es estudiante)
        if ($COURSE_ROLED_AS_STUDENT && $COURSE_ROLED_AS_STUDENT->id) {
            // --- LÓGICA PARA EL ESTUDIANTE ---

            // Verificar si el estudiante ya tiene una entrada en la tabla para este curso
            $entry = $DB->get_record('personality_test', array('user' => $USER->id, 'course' => $COURSE->id));

            if (!$entry) {
                // El estudiante NO ha realizado el test todavía - REDIRIGIR SIEMPRE
            		$redirect_url = new moodle_url('/blocks/personality_test/view.php', array('cid' => $COURSE->id));
            		redirect($redirect_url);
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

                    $mbti_explanations = array(
                        "ISTJ" => "práctica y centrada en los hechos, cuya fiabilidad no puede ser cuestionada.",
                        "ISFJ" => "protectora muy dedicada y cálida, siempre lista para defender a sus seres queridos.",
                        "INFJ" => "tranquila y mística, pero muy inspiradora e incansable idealista.",
                        "INTJ" => "visionaria, pensadora estratégica y resolvente de problemas lógicos.",
                        "ISTP" => "experimentadora audaz y práctica, maestra de todo tipo de herramientas.",
                        "ISFP" => "artistica flexible y encantadora, siempre dispuesta a explorar y experimentar algo nuevo.",
                        "INFP" => "poética, amable y altruista, siempre dispuesta por ayudar a una buena causa.",
                        "INTP" => "creativa e innovadora con una sed insaciable de conocimiento.",
                        "ESTP" => "inteligente, enérgica y muy perceptiva, que realmente disfruta viviendo al límite.",
                        "ESFP" => "espontánea, enérgica y entusiasta.",
                        "ENFP" => "de espíritu libre, entusiasta, creativa y sociable, que siempre pueden encontrar una razón para sonreír.",
                        "ENTP" => "pensadora, inteligente y curiosa, que no puede resistirse a un desafío intelectual.",
                        "ESTJ" => "práctica y centrada en los hechos, cuya fiabilidad no puede ser cuestionada.",
                        "ESFJ" => "extraordinariamente cariñosa, sociable y popular, siempre dispuesta a ayudar.",
                        "ENFJ" => "líder, carismática e inspiradora, capaz de cautivar a su audiencia.",
                        "ENTJ" => "líder, audaz, imaginativa y de voluntad fuerte, siempre encontrando una forma, o creándola."
                    );

                    $mbti_score = "";
                    $mbti_score .= ($scores["extraversion"] >= $scores["introversion"]) ? "E" : "I";
                    $mbti_score .= ($scores["sensing"] > $scores["intuition"]) ? "S" : "N";
                    $mbti_score .= ($scores["thinking"] >= $scores["feeling"]) ? "T" : "F";
                    $mbti_score .= ($scores["judging"] > $scores["perceptive"]) ? "J" : "P";

                    // --- CORRECCIÓN APLICADA AQUÍ ---
                    // Mostrar el resultado principal usando html_writer::tag
                    if (isset($mbti_explanations[$mbti_score])) {
                        // Construir el contenido del párrafo
                        $paragraph_content = "De acuerdo con el modelo de Myers Briggs todos tendemos a inclinarnos por cuatro facetas de personalidades predominantes.<br>";
                        $paragraph_content .= "En tu caso podemos concluir que eres una persona " . html_writer::tag('strong', $mbti_explanations[$mbti_score] . " (" . $mbti_score . ")");

                        // Usar html_writer::tag para crear el párrafo completo
                        $this->content->text .= html_writer::tag('p', $paragraph_content, ['class' => '']); // Puedes añadir clases CSS aquí si lo necesitas

                    } else {
                         $this->content->text .= get_string('error_puntuacion_invalida', 'block_personality_test') . " ($mbti_score)";
                    }
                    // --- FIN DE LA CORRECCIÓN ---

                    // Las interpretaciones detalladas están comentadas en tu código original, se mantienen así.
                 } // Fin else (datos de entry existen)
            } // Fin else (entry existe)
        } else {
            // --- LÓGICA PARA NO ESTUDIANTES (Podría ser Profesor u Otro Rol) ---

            $context = context_course::instance($COURSE->id);
            $is_teacher = has_capability('moodle/course:viewhiddensections', $context, $USER->id);

            if ($is_teacher) {
                // -- VISTA DEL PROFESOR --
                $teacher_content = $this->_get_teacher_content($DB, $COURSE);
                $this->content->text = $teacher_content->text;
                $this->content->footer = $teacher_content->footer;
            } else {
                // -- OTROS ROLES (Ni estudiante detectado, ni profesor con capacidad) --
                // Mantener la lógica original para estos casos: mostrar mensajes de configuración.
                 if (isset($this->config->personality_test_content) && !empty($this->config->personality_test_content["text"])) {
                    $this->content->text = "<img src='" . $OUTPUT->pix_url('ok', 'block_personality_test') . "'>" . get_string('personality_test_actived', 'block_personality_test');
                } else {
                    $this->content->text = "<img src='" . $OUTPUT->pix_url('warning', 'block_personality_test') . "'>" . get_string('personality_test_configempty', 'block_personality_test');
                }
            } // Fin else ($is_teacher)
        } // Fin else ($COURSE_ROLED_AS_STUDENT)

        // Devolver el objeto de contenido construido
        return $this->content;
    } // Fin get_content
}