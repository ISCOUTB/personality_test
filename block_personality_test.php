<?php

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

        $content = new stdClass();
        $content->text = '';
        $content->footer = '';

        // Header with personality test icon for teacher/admin view.
        $content->text .= '<div class="personality-header text-center mb-3">';
        $content->text .= $this->get_personality_test_icon('4em', '', true);
        $content->text .= '<h6 class="mt-2 mb-1 font-weight-bold">' . get_string('management_title', 'block_personality_test') . '</h6>';
        $content->text .= '<small class="text-muted">' . get_string('course_overview', 'block_personality_test') . '</small>';
        $content->text .= '</div>';

        // --- Vista del Profesor ---
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
        
        // Obtener respuestas solo de estudiantes inscritos que hayan COMPLETADO el test
        $students = array();
        if (!empty($student_ids)) {
            list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'user');
            $params['completed'] = 1;
            $sql = "SELECT * FROM {personality_test} WHERE user $insql AND is_completed = :completed";
            $students = $DB->get_records_sql($sql, $params);
        }

        // Si no hay tests completados, mostrar estadísticas básicas
        if(empty($students)) {
            // Contar tests en progreso
            $in_progress = 0;
            if (!empty($student_ids)) {
                list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'user');
                $params['completed'] = 0;
                $in_progress = $DB->count_records_select('personality_test', "user $insql AND is_completed = :completed", $params);
            }
            
            $total_students = count($student_ids);
            
            $content->text .= '<div class="alert alert-info" style="margin: 10px 0;">';
            $content->text .= '<h6 class="mb-2"><i class="fa fa-info-circle"></i> ' . get_string('participation_stats', 'block_personality_test') . '</h6>';
            $content->text .= '<ul class="mb-0 small" style="list-style: none; padding-left: 0;">';
            $content->text .= '<li><strong>' . get_string('total_participants', 'block_personality_test') . ':</strong> ' . $total_students . '</li>';
            $content->text .= '<li><strong>' . get_string('completed_tests', 'block_personality_test') . ':</strong> 0</li>';
            $content->text .= '<li><strong>' . get_string('in_progress_tests', 'block_personality_test') . ':</strong> ' . $in_progress . '</li>';
            $content->text .= '</ul>';
            $content->text .= '<hr style="margin: 10px 0;">';
            $content->text .= '<p class="mb-0 small"><i class="fa fa-chart-bar" style="color: #00bf91;"></i> <strong>' . get_string('sin_datos_estudiantes', 'block_personality_test') . '</strong></p>';
            $content->text .= '<p class="mb-0 mt-1 small text-muted" style="font-style: italic;">' . get_string('waiting_first_completion', 'block_personality_test') . '</p>';
            $content->text .= '</div>';
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
            if (!isset($entry->extraversion, $entry->introversion, $entry->sensing, $entry->intuition, $entry->thinking, $entry->feeling, $entry->judging, $entry->perceptive)) {
                continue;
            }

            $mbti_score = "";
            $mbti_score .= ($entry->extraversion > $entry->introversion) ? "E" : "I";
            $mbti_score .= ($entry->sensing > $entry->intuition) ? "S" : "N";
            $mbti_score .= ($entry->thinking >= $entry->feeling) ? "T" : "F";
            $mbti_score .= ($entry->judging > $entry->perceptive) ? "J" : "P";

            if (isset($mbti_count[$mbti_score])) {
                $mbti_count[$mbti_score]++;
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

        $mbti_json = json_encode(array_filter($mbti_count));
        $aspects_json = json_encode($aspect_counts);

        $str_titulo_distribucion_mbti = json_encode(get_string('titulo_distribucion_mbti', 'block_personality_test'));
        $str_num_estudiantes = json_encode(get_string('num_estudiantes', 'block_personality_test'));
        $str_introversion_extroversion = json_encode(get_string('introversion_extroversion', 'block_personality_test'));
        $str_sensacion_intuicion = json_encode(get_string('sensacion_intuicion', 'block_personality_test'));
        $str_pensamiento_sentimiento = json_encode(get_string('pensamiento_sentimiento', 'block_personality_test'));
        $str_juicio_percepcion = json_encode(get_string('juicio_percepcion', 'block_personality_test'));

        // Mostrar estadísticas de participación
        $total_enrolled = count($student_ids);
        $total_completed = count($students);
        $completion_percentage = $total_enrolled > 0 ? round(($total_completed / $total_enrolled) * 100, 1) : 0;
        
        $participation_data = new stdClass();
        $participation_data->completed = $total_completed;
        $participation_data->total = $total_enrolled;
        $participation_data->percentage = $completion_percentage;
        
        $content->text .= html_writer::start_div('alert alert-info', ['style' => 'margin-bottom: 15px; padding: 10px; text-align: center;']);
        $content->text .= html_writer::tag('strong', get_string('participation_stats', 'block_personality_test') . ': ');
        $content->text .= html_writer::tag('span', get_string('students_completed_test', 'block_personality_test', $participation_data));
        $content->text .= html_writer::end_div();

        $content->text .= html_writer::tag('h6',get_string('titulo_resultados_estudiantes', 'block_personality_test'),['style' => 'text-align: center;']);
        $content->text .= html_writer::tag('canvas', '', ['id' => 'mbtiChart', 'style' => 'max-width: 100%; max-height: 350px; height: auto;']);        $content->text .= html_writer::tag('h6', get_string('titulo_distribucion_rasgos', 'block_personality_test'), ['style' => 'text-align: center; margin-top: 20px;']);

        $content->text .= html_writer::start_div('d-flex flex-wrap justify-content-around');
        $chart_style = "width: 100%; max-width: 350px; margin: 10px; box-sizing: border-box;";
        $content->text .= html_writer::div(html_writer::tag('canvas', '', ['id' => 'generalTrendChart']), '', ['style' => $chart_style]);
        $content->text .= html_writer::div(html_writer::tag('canvas', '', ['id' => 'infoProcessingChart']), '', ['style' => $chart_style]);
        $content->text .= html_writer::div(html_writer::tag('canvas', '', ['id' => 'decisionMakingChart']), '', ['style' => $chart_style]);
        $content->text .= html_writer::div(html_writer::tag('canvas', '', ['id' => 'organizationChart']), '', ['style' => $chart_style]);
        $content->text .= html_writer::end_div();

        // Preparar datos para JavaScript
        $strings = array(
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

        $js_code = "
        require(['core/chartjs'], function(Chart) {
            var mbtiData = " . $mbti_json . ";
            var aspectData = " . json_encode($aspect_counts) . ";
            var strings = " . json_encode($strings) . ";

            // Paleta de colores SAVIO UTB
            const colorPalette = {
                mbti: [
                    '#005B9A', '#FF8200', '#FFB600', '#00B5E2', 
                    '#78BE20', '#2C5234', '#652C8F', '#91268F', 
                    '#D0006F', '#AA182C', '#8B0304', '#E35205', 
                    '#385CAD', '#0077C8', '#00263A', '#00A9B7'
                ],
                introversion: ['#005B9A', '#FF8200'],
                sensacion: ['#00B5E2', '#FFB600'],
                pensamiento: ['#78BE20', '#652C8F'],
                juicio: ['#AA182C', '#0077C8']
            };

            // Función para crear gráfico de pie MBTI
            function createMBTIChart() {
                var ctxPie = document.getElementById('mbtiChart');
                if (ctxPie) {
                    ctxPie = ctxPie.getContext('2d');
                    
                    const mbtiLabels = [];
                    const mbtiValues = [];
                    const mbtiColors = [];
                    
                    let colorIndex = 0;
                    if (typeof mbtiData === 'string') {
                        try { mbtiData = JSON.parse(mbtiData); } catch (e) {}
                    }
                    
                    Object.keys(mbtiData).forEach(key => {
                        if (mbtiData[key] > 0) {
                            mbtiLabels.push(key);
                            mbtiValues.push(mbtiData[key]);
                            mbtiColors.push(colorPalette.mbti[colorIndex % colorPalette.mbti.length]);
                            colorIndex++;
                        }
                    });
                    
                    if (mbtiLabels.length === 0) {
                        document.getElementById('mbtiChart').parentNode.innerHTML = 
                            '<div class=\"alert alert-info text-center\" style=\"margin-top: 20px;\">' + 
                            strings.sin_datos_estudiantes + '</div>';
                        return;
                    }
                    
                    new Chart(ctxPie, {
                        type: 'pie',
                        data: {
                            labels: mbtiLabels,
                            datasets: [{
                                data: mbtiValues,
                                backgroundColor: mbtiColors,
                                borderColor: '#ffffff',
                                borderWidth: 1,
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                title: {
                                    display: true,
                                    text: strings.titulo_distribucion_mbti,
                                    font: { size: 14, weight: '500' },
                                    padding: { top: 5, bottom: 15 }
                                },
                                legend: {
                                    position: 'top',
                                    labels: { boxWidth: 15, padding: 8, font: { size: 11 } }
                                },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 43, 73, 0.8)',
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            if (label) { label += ': '; }
                                            if (context.parsed !== null) {
                                                let total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                                let percentage = Math.round((context.parsed / total) * 100);
                                                label += context.parsed + ' (' + percentage + '%)';
                                            }
                                            return label;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Función para crear gráficos de barras
            function createBarChart(elementId, title, labels, data, colors) {
                var ctx = document.getElementById(elementId);
                if (ctx) {
                    ctx = ctx.getContext('2d');
                    
                    if (!data || !data.length || (data[0] === 0 && data[1] === 0)) {
                        document.getElementById(elementId).parentNode.innerHTML = 
                            '<div class=\"alert alert-info text-center\" style=\"margin-top: 10px;\">' + 
                            strings.sin_datos_estudiantes + '</div>';
                        return;
                    }
                    
                    const maxValue = Math.max(...data);
                    const yMax = Math.ceil(maxValue * 1.1);
                    
                    new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: strings.num_estudiantes_header,
                                data: data,
                                backgroundColor: colors,
                                borderColor: colors,
                                borderWidth: 0,
                                borderRadius: 2,
                                barPercentage: 0.8,
                                categoryPercentage: 0.9
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            indexAxis: 'x',
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    max: yMax,
                                    ticks: { stepSize: 1, precision: 0, font: { size: 11 } },
                                    grid: { color: 'rgba(0, 43, 73, 0.05)', drawBorder: false }
                                },
                                x: {
                                    grid: { display: false, drawBorder: false },
                                    ticks: { font: { size: 11 } }
                                }
                            },
                            plugins: {
                                title: {
                                    display: true,
                                    text: title,
                                    font: { size: 14, weight: '500' },
                                    padding: { top: 5, bottom: 15 }
                                },
                                legend: { display: false },
                                tooltip: {
                                    backgroundColor: 'rgba(0, 43, 73, 0.8)',
                                    callbacks: {
                                        label: function(context) {
                                            return strings.num_estudiantes_header + ': ' + context.parsed.y;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            }

            // Crear todas las gráficas
            createMBTIChart();

            createBarChart(
                'generalTrendChart',
                strings.introversion_extroversion,
                [strings.Introvertido, strings.Extrovertido],
                [aspectData.Introvertido || 0, aspectData.Extrovertido || 0],
                colorPalette.introversion
            );

            createBarChart(
                'infoProcessingChart',
                strings.sensacion_intuicion,
                [strings.Sensing, strings.Intuicion],
                [aspectData.Sensing || 0, aspectData.Intuición || 0],
                colorPalette.sensacion
            );

            createBarChart(
                'decisionMakingChart',
                strings.pensamiento_sentimiento,
                [strings.Pensamiento, strings.Sentimiento],
                [aspectData.Pensamiento || 0, aspectData.Sentimiento || 0],
                colorPalette.pensamiento
            );

            createBarChart(
                'organizationChart',
                strings.juicio_percepcion,
                [strings.Juicio, strings.Percepcion],
                [aspectData.Juicio || 0, aspectData.Percepción || 0],
                colorPalette.juicio
            );
        });
        ";
        
        $this->page->requires->js_init_code($js_code);

        $csv_url = new moodle_url('/blocks/personality_test/download_csv.php', ['courseid' => $COURSE->id, 'sesskey' => sesskey()]);
        $pdf_url = new moodle_url('/blocks/personality_test/download_pdf.php', ['courseid' => $COURSE->id, 'sesskey' => sesskey()]);

        $download_links = html_writer::start_div('text-center', ['style' => 'margin-top: 20px;']);
        $link_attributes = [
            'class' => 'btn btn-sm d-inline-block',
            'style' => 'margin: 5px; background-color: #1e7e34; color: #ffffff; text-decoration: none; border: none;',
            'role' => 'button'
        ];
        $download_links .= html_writer::link($csv_url, get_string('CSV', 'block_personality_test'), $link_attributes);
        $download_links .= html_writer::link($pdf_url, get_string('PDF', 'block_personality_test'), $link_attributes);
        $download_links .= html_writer::end_div();

        $content->text .= $download_links;
        $content->footer = '';
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
        $iconurl = new moodle_url('/blocks/personality_test/pix/personality_test_icon.svg');
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
        global $COURSE;
        
        $output = '';
        $output .= '<div class="personality-invitation-block">';
        
        // Header with personality test icon
        $output .= '<div class="personality-header text-center mb-3">';
        $output .= $this->get_personality_test_icon('4em', '', true);
        $output .= '<h6 class="mt-2 mb-1">' . get_string('test_title', 'block_personality_test') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('discover_your_personality', 'block_personality_test') . '</small>';
        $output .= '</div>';
        
        // Test description card
        $output .= '<div class="personality-description mb-3">';
        $output .= '<div class="card border-info">';
        $output .= '<div class="card-body p-3">';
        $output .= '<h6 class="card-title">';
        $output .= '<i class="fa fa-info-circle" style="color: #00bf91;"></i> ';
        $output .= get_string('what_is_mbti', 'block_personality_test');
        $output .= '</h6>';
        $output .= '<p class="card-text small mb-2">' . get_string('test_description', 'block_personality_test') . '</p>';
        $output .= '<ul class="list-unstyled small mb-0">';
        $output .= '<li><i class="fa fa-check" style="color: #00bf91;"></i> ' . get_string('feature_72_questions', 'block_personality_test') . '</li>';
        $output .= '<li><i class="fa fa-check text-success"></i> ' . get_string('feature_16_types', 'block_personality_test') . '</li>';
        $output .= '<li><i class="fa fa-check text-success"></i> ' . get_string('feature_instant_results', 'block_personality_test') . '</li>';
        $output .= '</ul>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Action button
        $output .= '<div class="personality-actions text-center">';
        $url = new moodle_url('/blocks/personality_test/view.php', array('cid' => $COURSE->id));
        $output .= '<a href="' . $url . '" class="btn btn-info btn-block">';
        $output .= '<i class="fa fa-rocket"></i> <span>' . get_string('start_test', 'block_personality_test') . '</span>';
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Display continue test card for students with test in progress
     * Design matches learning_style block for consistency
     */
    private function get_continue_test_card($answered_count) {
        global $COURSE;
        
        $output = '';
        $progress_percentage = ($answered_count / 72) * 100;
        $all_answered = ($answered_count >= 72);
        
        $output .= '<div class="personality-invitation-block" style="padding: 15px; background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%); border-radius: 8px; border: 1px solid #dee2e6;">';
        
        // Header with personality test icon
        $output .= '<div class="personality-header text-center mb-3">';
        $output .= $this->get_personality_test_icon('4em', 'text-shadow: 0 1px 2px rgba(0,0,0,0.1);', true);
        $output .= '<h6 class="mt-2 mb-1">' . get_string('test_title', 'block_personality_test') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('discover_your_personality', 'block_personality_test') . '</small>';
        $output .= '</div>';
        
        // Description section
        $output .= '<div class="personality-description mb-3" style="background: white; padding: 10px 12px; border-radius: 5px; border-left: 3px solid #00bf91;">';
        $output .= '<small class="text-muted" style="line-height: 1.5;">';
        $output .= '<i class="fa fa-info-circle" style="color: #00bf91;"></i> ';
        $output .= get_string('test_description', 'block_personality_test');
        $output .= '</small>';
        $output .= '</div>';
        
        // Special alert if all questions answered but not submitted
        if ($all_answered) {
            $output .= '<div class="alert alert-warning mb-3" style="padding: 12px 15px; margin-bottom: 15px; border-left: 4px solid #ffc107; background-color: #fff3cd; border-radius: 4px;">';
            $output .= '<div style="display: flex; align-items: start;">';
            $output .= '<i class="fa fa-exclamation-triangle" style="color: #856404; margin-right: 10px; margin-top: 2px; font-size: 1.2em;"></i>';
            $output .= '<div>';
            $output .= '<strong style="color: #856404;">' . get_string('all_answered_title', 'block_personality_test') . '</strong><br>';
            $output .= '<small style="color: #856404;">' . get_string('all_answered_message', 'block_personality_test') . '</small>';
            $output .= '</div>';
            $output .= '</div>';
            $output .= '</div>';
        }
        
        // Progress section
        $output .= '<div class="personality-progress mb-3" style="background: white; padding: 12px; border-radius: 5px; border: 1px solid #e9ecef;">';
        $output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $output .= '<span class="small font-weight-bold">' . get_string('your_progress', 'block_personality_test') . '</span>';
        $output .= '<span class="small text-muted">' . $answered_count . '/72</span>';
        $output .= '</div>';
        $output .= '<div class="progress mb-2" style="height: 8px;">';
        $output .= '<div class="progress-bar" style="width: ' . $progress_percentage . '%; background: linear-gradient(135deg, #00dda9ff 0%, #00a07a 100%);"></div>';
        $output .= '</div>';
        $output .= '<small class="text-muted">' . number_format($progress_percentage, 1) . '% ' . get_string('completed', 'block_personality_test') . '</small>';
        $output .= '</div>';
        
        // Call to action button
        $output .= '<div class="personality-actions text-center">';
        
        if ($all_answered) {
            // Go to last page (8) with scroll parameter for finish button
            $url = new moodle_url('/blocks/personality_test/view.php', array('cid' => $COURSE->id, 'page' => 8, 'scroll_to_finish' => 1));
            $output .= '<a href="' . $url . '" class="btn btn-success btn-block" style="box-shadow: 0 2px 4px rgba(0,0,0,0.2); font-weight: 500; transition: all 0.3s ease;">';
            $output .= '<i class="fa fa-flag-checkered"></i> ' . get_string('finish_test_now', 'block_personality_test');
            $output .= '</a>';
        } else {
            // Go to page with first unanswered question
            $url = new moodle_url('/blocks/personality_test/view.php', array('cid' => $COURSE->id));
            $output .= '<a href="' . $url . '" class="btn btn-info btn-block" style="box-shadow: 0 2px 4px rgba(0,0,0,0.2); font-weight: 500; transition: all 0.3s ease;">';
            $output .= '<i class="fa fa-play"></i> ' . get_string('continue_test', 'block_personality_test');
            $output .= '</a>';
        }
        
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
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

        $context = context_course::instance($COURSE->id);

        // Si el usuario tiene permiso de ver reportes, mostrar la vista del profesor/administración.
        if (has_capability('block/personality_test:viewreports', $context)) {
            $teacher_content = $this->_get_teacher_content($DB, $COURSE);
            $this->content = $teacher_content;
            // Agregar enlace a la vista administrativa en el footer para consistencia
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
            // --- LÓGICA PARA EL ESTUDIANTE ---

            // Verificar si el estudiante ya tiene una entrada en la tabla (en cualquier curso)
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

                    // IMPORTANTE: El radar debe graficar los puntajes reales del estudiante,
                    // no valores fijos por tipo MBTI.

                    // Header con icono de éxito
                    $this->content->text .= '<div class="personality-results-block" style="padding: 15px; background: white; border-radius: 8px; border: 1px solid #dee2e6;">';
                    $this->content->text .= '<div class="personality-header text-center mb-3">';
                    $this->content->text .= '<div style="position: relative; display: inline-block; line-height: 0;">';
                    $this->content->text .= $this->get_personality_test_icon('4em', 'display: block;', false);
                    $this->content->text .= '<i class="fa fa-check" style="position: absolute; top: -6px; right: -9px; font-size: 1.4em; background: white; border-radius: 50%; line-height: 1; text-shadow: 0 1px 2px rgba(0,0,0,0.1); color: #00bf91;"></i>';
                    $this->content->text .= '</div>';
                    $this->content->text .= '<h6 class="mt-2 mb-1">' . get_string('test_completed', 'block_personality_test') . '</h6>';
                    $this->content->text .= '<small class="text-muted">' . get_string('your_personality_type', 'block_personality_test') . '</small>';
                    $this->content->text .= '</div>';
                    
                    // Test description
                    $this->content->text .= '<div class="personality-description mb-3" style="background: #f8f9fa; padding: 10px 12px; border-radius: 5px; border-left: 3px solid #00bf91;">';
                    $this->content->text .= '<small class="text-muted" style="line-height: 1.5;">';
                    $this->content->text .= '<i class="fa fa-info-circle" style="color: #00bf91;"></i> ';
                    $this->content->text .= get_string('test_description', 'block_personality_test');
                    $this->content->text .= '</small>';
                    $this->content->text .= '</div>';

                    // Tipo MBTI del estudiante
                    $this->content->text .= '<div class="mb-3 text-center">';
                    $this->content->text .= '<h3 class="mb-2" style="color: #00bf91;"><strong>' . $mbti_score . '</strong></h3>';
                    $mbti_dimensions_key = 'mbti_dimensions_' . strtolower($mbti_score);
                    $this->content->text .= '<small class="text-muted">' . get_string($mbti_dimensions_key, 'block_personality_test') . '</small>';
                    $this->content->text .= '</div>';
                    
                    // Descripción detallada del tipo MBTI usando strings de idioma
                    $mbti_key = 'mbti_' . strtolower($mbti_score);
                    $this->content->text .= '<p class="mb-3" style="text-align: justify; line-height: 1.6;">';
                    $this->content->text .= get_string($mbti_key, 'block_personality_test');
                    $this->content->text .= '</p>';

                    // Definimos las etiquetas de las dimensiones para el gráfico usando strings de idioma
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

                    // El orden de los datos DEBE corresponder exactamente al orden de $mbti_labels.
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

                    // Creamos un ID único para el canvas del gráfico usando el ID del usuario
                    $chart_id = 'graficoRadar_' . $USER->id;

                    // Insertamos el <canvas> donde se dibujará el gráfico radar
                    $this->content->text .= html_writer::start_tag('canvas', [
                        'id' => $chart_id,
                        'width' => '400',
                        'height' => '400'
                    ]);
                    $this->content->text .= html_writer::end_tag('canvas');

                    // Agregamos el script JavaScript que dibuja el gráfico usando Chart.js
                    $js_code = "
                    require(['core/chartjs'], function(Chart) {
                        var chartId = " . json_encode($chart_id) . ";
                        var labels = " . json_encode($mbti_labels) . ";
                        var data = " . json_encode($user_data) . ";
                        var datasetLabel = " . json_encode(get_string('mbti_type', 'block_personality_test') . ': ' . $mbti_score) . ";

                        var el = document.getElementById(chartId);
                        if(!el) return;
                        var ctx = el.getContext('2d');
                        
                        new Chart(ctx, {
                            type: 'radar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: datasetLabel,
                                    data: data,
                                    backgroundColor: 'rgba(0, 191, 145, 0.2)',
                                    borderColor: 'rgba(0, 191, 145, 1)',
                                    pointBackgroundColor: 'rgba(0, 191, 145, 1)',
                                    pointBorderColor: '#fff',
                                    pointHoverBackgroundColor: '#fff',
                                    pointHoverBorderColor: 'rgba(0, 191, 145, 1)'
                                }]
                            },
                            options: {
                                scales: {
                                    r: {
                                        beginAtZero: true,
                                        min: 0,
                                        max: 9,
                                        angleLines: { 
                                            display: true,
                                            color: 'rgba(0, 0, 0, 0.1)'
                                        },
                                        grid: {
                                            // AQUÍ QUITAMOS LAS LÍNEAS PARES
                                            color: function(context) {
                                                if (context.tick.value % 2 === 1) {
                                                    return 'transparent';
                                                }
                                                return 'rgba(0, 0, 0, 0.1)';
                                            },
                                            lineWidth: 1
                                        },
                                        ticks: {
                                            stepSize: 1,
                                            display: true,
                                            backdropColor: 'transparent',
                                            // TAMBIÉN QUITAMOS LOS NÚMEROS PARES DE LAS ETIQUETAS
                                            callback: function(value) {
                                                return value % 2 !== 0 ? value : '';
                                            },
                                            font: {
                                                size: 12
                                            }
                                        },  
                                        pointLabels: {
                                            font: {size: 14}
                                        }
                                    }
                                }
                            }
                        });
                    });";
                    
                    $this->page->requires->js_init_code($js_code);
                    
                    // Cerrar div de resultados
                    $this->content->text .= '</div>';

                    // Retornamos el contenido del bloque
                    return $this->content;
                 } // Fin else (datos de entry existen)
            } // Fin else (entry existe)
        }

        // Devolver el objeto de contenido construido
        return $this->content;
    } // Fin get_content
}
