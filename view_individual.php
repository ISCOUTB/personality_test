<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$courseid = required_param('cid', PARAM_INT);

// Verificar permisos: solo administradores y profesores
$isadmin = is_siteadmin($USER);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Silent redirect if user lacks report capability
if (!has_capability('block/personality_test:viewreports', $context)) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

// Obtener datos del usuario y test
$userfields = \core_user\fields::for_name()->with_userpic()->get_sql('', false, '', '', false)->selects;
$user = $DB->get_record('user', array('id' => $userid), $userfields, MUST_EXIST);
$test_result = $DB->get_record('personality_test', array('user' => $userid));

// Prevent teachers from viewing students outside their groups unless they can access all groups
if (!is_siteadmin($USER)) {
    // If the current user has accessallgroups, allow
    if (!has_capability('moodle/site:accessallgroups', $context)) {
        // Get groups for teacher and target student
        list($teachergroups, $tg) = groups_get_user_groups($courseid, $USER->id);
        list($studentgroups, $sg) = groups_get_user_groups($courseid, $userid);

        // If both have groups defined and no intersection, redirect silently
        if (!empty($teachergroups) && !empty($studentgroups)) {
            $common = array_intersect($teachergroups, $studentgroups);
            if (empty($common)) {
                redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
            }
        }
        // If teacher has no groups but student does, block access (teacher not in student's group)
        if (empty($teachergroups) && !empty($studentgroups)) {
            redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
        }
    }
}

if (!$test_result) {
    redirect(new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)), 
             get_string('no_test_results', 'block_personality_test'));
}

// Configurar página
$PAGE->set_url(new moodle_url('/blocks/personality_test/view_individual.php', array('userid' => $userid, 'cid' => $courseid)));
$PAGE->set_context($context);
$PAGE->set_title(get_string('individual_results', 'block_personality_test') . ': ' . fullname($user));
$PAGE->set_heading(get_string('individual_results', 'block_personality_test') . ': ' . fullname($user));

$PAGE->requires->css(new moodle_url('/blocks/personality_test/styles.css'));

echo $OUTPUT->header();

// Verificar si el test está completado
if ($test_result->is_completed == 0) {
    // Mostrar alerta de progreso
    echo "<div class='container-fluid'>";
    echo "<div class='alert alert-warning' role='alert'>";
    echo "<h4 class='alert-heading'><i class='fa fa-clock-o'></i> " . get_string('test_in_progress', 'block_personality_test') . "</h4>";
    echo "<p>" . get_string('test_in_progress_message', 'block_personality_test', fullname($user)) . "</p>";
    echo "<hr>";
    
    // Calcular progreso
    $answered = 0;
    for ($i = 1; $i <= 72; $i++) {
        $field = 'q' . $i;
        if (isset($test_result->$field) && $test_result->$field !== null && $test_result->$field !== '') {
            $answered++;
        }
    }
    
    $progress_percentage = round(($answered / 72) * 100, 1);
    
    echo "<p class='mb-1'><strong>" . get_string('progress_label', 'block_personality_test') . ":</strong></p>";
    echo "<div class='progress mb-2' style='height: 30px;'>";
    echo "<div class='progress-bar bg-warning' role='progressbar' style='width: " . $progress_percentage . "%' aria-valuenow='" . $progress_percentage . "' aria-valuemin='0' aria-valuemax='100'>";
    echo "<strong>" . $progress_percentage . "%</strong>";
    echo "</div>";
    echo "</div>";
    echo "<p><strong>" . get_string('has_answered', 'block_personality_test') . ":</strong> " . get_string('of_72_questions', 'block_personality_test', $answered) . "</p>";
    
    // Special message if all questions answered but not submitted
    if ($answered == 72) {
        echo "<div class='alert alert-info mt-2' role='alert'>";
        echo "<i class='fa fa-info-circle'></i> ";
        echo "<strong>" . get_string('remind_submit_test', 'block_personality_test') . "</strong>";
        echo "</div>";
    }
    
    echo "<p class='mb-0'><em>" . get_string('results_available_when_complete', 'block_personality_test', fullname($user)) . "</em></p>";
    echo "</div>";
    
    // Botón para volver
    echo "<div class='mt-4'>";
    echo "<a href='" . new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)) . "' class='btn btn-secondary'>";
    echo "<i class='fa fa-arrow-left'></i> " . get_string('back_to_admin', 'block_personality_test');
    echo "</a>";
    echo "</div>";
    echo "</div>";
    
    echo $OUTPUT->footer();
    exit;
}

// Si llegamos aquí, el test está completado, mostrar resultados completos
// Calcular tipo MBTI
$mbti = '';
$mbti .= ($test_result->extraversion > $test_result->introversion) ? 'E' : 'I';
$mbti .= ($test_result->sensing > $test_result->intuition) ? 'S' : 'N';
$mbti .= ($test_result->thinking >= $test_result->feeling) ? 'T' : 'F';
$mbti .= ($test_result->judging > $test_result->perceptive) ? 'J' : 'P';

echo "<div class='container-fluid'>";

// Header con información del estudiante
echo "<div class='row mb-4'>";
echo "<div class='col-12'>";
echo "<div class='card'>";
echo "<div class='card-header' style='background: linear-gradient(135deg, #00bf91 0%, #00a07a 100%); color: white;'>";
echo "<h3 class='card-title mb-0'>";
$iconurl = new moodle_url('/blocks/personality_test/pix/personality_test_icon.svg');
echo "<span style='color: #ffffff;'><img src='" . $iconurl . "' alt='Personality Test Icon' style='width: 30px; height: 30px; vertical-align: middle; margin-right: 10px;' />" . get_string('individual_results', 'block_personality_test') . "</span>";
echo "</h3>";
echo "</div>";
echo "<div class='card-body'>";
echo "<div class='row'>";
echo "<div class='col-md-8'>";
echo "<h4>" . fullname($user) . "</h4>";
echo "<p class='text-muted mb-1'><strong>" . get_string('email', 'block_personality_test') . ":</strong> " . $user->email . "</p>";
echo "<p class='text-muted mb-1'><strong>" . get_string('test_date', 'block_personality_test') . ":</strong> " . date('d/m/Y H:i', $test_result->created_at) . "</p>";
echo "<p class='text-muted mb-1'><strong>" . get_string('mbti_type', 'block_personality_test') . ":</strong> " . $mbti . "</p>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

// Resultados detallados
echo "<div class='row'>";

// Dimensiones principales
echo "<div class='col-lg-8 mb-4'>";
echo "<div class='card h-100'>";
echo "<div class='card-header' style='background: linear-gradient(135deg, #e0f7f1 0%, #f8f9fa 100%) !important; color: #00bf91 !important;'>";
echo "<h5 class='mb-0'><i class='fa fa-chart-bar'></i> " . get_string('personality_dimensions', 'block_personality_test') . "</h5>";
echo "</div>";
echo "<div class='card-body'>";

// Función para mostrar barras de progreso comparativas
function render_dimension_bar($label1, $value1, $label2, $value2, $max_value = 100) {
    $total = $value1 + $value2;
    $percent1 = $total > 0 ? ($value1 / $total) * 100 : 50;
    $percent2 = $total > 0 ? ($value2 / $total) * 100 : 50;
    
    echo "<div class='mb-4'>";
    echo "<div class='d-flex justify-content-between mb-2'>";
    echo "<span><strong>" . $label1 . "</strong> (" . $value1 . ")</span>"; 
    echo "<span><strong>" . $label2 . "</strong> (" . $value2 . ")</span>";
    echo "</div>";
    echo "<div class='progress' style='height: 25px;'>";
    echo "<div class='progress-bar' style='width: " . $percent1 . "%; background-color: #00bf91; border-right: 2px solid rgba(255,255,255,0.85);' role='progressbar' aria-valuenow='" . $percent1 . "' aria-valuemin='0' aria-valuemax='100'>";
    echo round($percent1, 1) . "%";
    echo "</div>";
    echo "<div class='progress-bar' style='width: " . $percent2 . "%; background-color: #00d9a8;' role='progressbar' aria-valuenow='" . $percent2 . "' aria-valuemin='0' aria-valuemax='100'>";
    echo round($percent2, 1) . "%";
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

render_dimension_bar(
    get_string('extraversion', 'block_personality_test'), 
    $test_result->extraversion,
    get_string('introversion', 'block_personality_test'), 
    $test_result->introversion
);

render_dimension_bar(
    get_string('sensing', 'block_personality_test'), 
    $test_result->sensing,
    get_string('intuition', 'block_personality_test'), 
    $test_result->intuition
);

render_dimension_bar(
    get_string('thinking', 'block_personality_test'), 
    $test_result->thinking,
    get_string('feeling', 'block_personality_test'), 
    $test_result->feeling
);

render_dimension_bar(
    get_string('judging', 'block_personality_test'), 
    $test_result->judging,
    get_string('perceptive', 'block_personality_test'), 
    $test_result->perceptive
);

echo "</div>";
echo "</div>";
echo "</div>";

// Resumen y acciones
echo "<div class='col-lg-4 mb-4'>";
echo "<div class='card h-100'>";
echo "<div class='card-header' style='background: linear-gradient(135deg, #e0f7f1 0%, #f8f9fa 100%) !important; color: #00bf91 !important;'>";
echo "<h5 class='mb-0'><i class='fa fa-info-circle'></i> " . get_string('summary_actions', 'block_personality_test') . "</h5>";
echo "</div>";
echo "<div class='card-body'>";

// Usar cadenas de idioma para las descripciones MBTI
$mbti_key = 'mbti_' . strtolower($mbti);
$mbti_dimensions_key = 'mbti_dimensions_' . strtolower($mbti);

echo "<div class='text-center mb-4'>";
echo "<h2 class='oficial-color mb-2'>" . $mbti . "</h2>";
echo "<p class='text-muted' style='font-size: 0.9em;'>(" . get_string($mbti_dimensions_key, 'block_personality_test') . ")</p>";
echo "<p class='text-muted' style='text-align: justify;'>" . get_string($mbti_key, 'block_personality_test') . "</p>";
echo "</div>";

echo "<div class='d-flex justify-content-center gap-2'>";
echo "<a href='" . new moodle_url('/blocks/personality_test/download_pdf.php', 
        array('userid' => $userid, 'cid' => $courseid)) . 
        "' class='btn mr-3' style='background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%) !important; border: none; color: white;'>";
echo "<i class='fa fa-download'></i> " . get_string('download_pdf', 'block_personality_test');
echo "</a>";

echo "<a href='" . new moodle_url('/blocks/personality_test/admin_view.php', 
        array('cid' => $courseid, 'action' => 'delete', 'userid' => $userid, 'sesskey' => sesskey())) . 
        "' class='btn btn-danger' onclick='return confirm(\"" . get_string('confirm_delete_individual', 'block_personality_test') . "\")'>";
echo "<i class='fa fa-trash'></i> " . get_string('delete_results', 'block_personality_test');
echo "</a>";
echo "</div>";

echo "</div>";
echo "</div>";
echo "</div>";

echo "</div>";

// Datos detallados en tabla
echo "<div class='row'>";
echo "<div class='col-12'>";
echo "<div class='card'>";
echo "<div class='card-header' style='background: linear-gradient(135deg, #e0f7f1 0%, #f8f9fa 100%) !important; color: #00bf91 !important;'>";
echo "<h5 class='mb-0'><i class='fa fa-table'></i> " . get_string('detailed_scores', 'block_personality_test') . "</h5>";
echo "</div>";
echo "<div class='card-body'>";
echo "<div class='table-responsive'>";
echo "<table class='table table-striped'>";
echo "<thead>";
echo "<tr>";
echo "<th>" . get_string('dimension', 'block_personality_test') . "</th>";
echo "<th>" . get_string('score', 'block_personality_test') . "</th>";
echo "<th>" . get_string('preference', 'block_personality_test') . "</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

$dimensions = [
    [get_string('extraversion', 'block_personality_test'), get_string('introversion', 'block_personality_test'), $test_result->extraversion, $test_result->introversion],
    [get_string('sensing', 'block_personality_test'), get_string('intuition', 'block_personality_test'), $test_result->sensing, $test_result->intuition],
    [get_string('thinking', 'block_personality_test'), get_string('feeling', 'block_personality_test'), $test_result->thinking, $test_result->feeling],
    [get_string('judging', 'block_personality_test'), get_string('perceptive', 'block_personality_test'), $test_result->judging, $test_result->perceptive]
];

foreach ($dimensions as $dim) {
    $label1 = $dim[0];
    $label2 = $dim[1];
    $value1 = $dim[2];
    $value2 = $dim[3];
    $dominant = ($value1 >= $value2) ? $label1 : $label2;
    echo "<tr>";
    echo "<td><strong>" . $label1 . " - " . $label2 . "</strong></td>";
    echo "<td>" . $value1 . " - " . $value2 . "</td>";
    echo "<td>" . $dominant . "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "</div>";

// Botones de navegación con diseño moderno
echo html_writer::start_div('mt-5 text-center d-flex gap-3 justify-content-center');
echo html_writer::link(
    new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)),
    '<i class="fa fa-arrow-left mr-2"></i>' . get_string('back_to_admin', 'block_personality_test'),
    array('class' => 'btn btn-secondary btn-modern mr-3')
);
echo html_writer::link(
    new moodle_url('/course/view.php', array('id' => $courseid)),
    '<i class="fa fa-home mr-2"></i>' . get_string('back_to_course', 'block_personality_test'),
    array('class' => 'btn btn-modern', 'style' => 'background: linear-gradient(135deg, #00bf91 0%, #00a07a 100%); border: none; color: white;')
);
echo html_writer::end_div();

echo $OUTPUT->footer();
?>
