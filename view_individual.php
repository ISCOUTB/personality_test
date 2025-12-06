<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$courseid = required_param('cid', PARAM_INT);

// Verificar permisos: solo administradores y profesores
$isadmin = is_siteadmin($USER);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

$COURSE_ROLED_AS_TEACHER = $DB->get_record_sql("
    SELECT m.id
    FROM {user} m 
    LEFT JOIN {role_assignments} m2 ON m.id = m2.userid 
    LEFT JOIN {context} m3 ON m2.contextid = m3.id 
    LEFT JOIN {course} m4 ON m3.instanceid = m4.id 
    WHERE (m3.contextlevel = 50 AND m2.roleid IN (3, 4) AND m.id IN ({$USER->id})) 
    AND m4.id = {$courseid} 
");

if (!$isadmin && (!isset($COURSE_ROLED_AS_TEACHER->id) || !$COURSE_ROLED_AS_TEACHER->id)) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)), 
             get_string('no_admin_access', 'block_personality_test'));
}

// Obtener datos del usuario y test
$userfields = \core_user\fields::for_name()->with_userpic()->get_sql('', false, '', '', false)->selects;
$user = $DB->get_record('user', array('id' => $userid), $userfields, MUST_EXIST);
$test_result = $DB->get_record('personality_test', array('user' => $userid, 'course' => $courseid));

if (!$test_result) {
    redirect(new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)), 
             get_string('no_test_results', 'block_personality_test'));
}

// Calcular tipo MBTI
$mbti = '';
$mbti .= ($test_result->extraversion >= $test_result->introversion) ? 'E' : 'I';
$mbti .= ($test_result->sensing > $test_result->intuition) ? 'S' : 'N';
$mbti .= ($test_result->thinking >= $test_result->feeling) ? 'T' : 'F';
$mbti .= ($test_result->judging > $test_result->perceptive) ? 'J' : 'P';

// Configurar página
$PAGE->set_url(new moodle_url('/blocks/personality_test/view_individual.php', array('userid' => $userid, 'cid' => $courseid)));
$PAGE->set_context($context);
$PAGE->set_title(get_string('individual_results', 'block_personality_test') . ': ' . fullname($user));
$PAGE->set_heading(get_string('individual_results', 'block_personality_test') . ': ' . fullname($user));

echo $OUTPUT->header();

// CSS personalizado
echo "<link rel='stylesheet' href='" . $CFG->wwwroot . "/blocks/personality_test/styles.css'>";

// Breadcrumb navigation
echo '<nav aria-label="breadcrumb" class="mb-4">';
echo '<ol class="breadcrumb">';
echo '<li class="breadcrumb-item"><a href="' . new moodle_url('/course/view.php', array('id' => $courseid)) . '">' . $course->fullname . '</a></li>';
echo '<li class="breadcrumb-item"><a href="' . new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)) . '">' . get_string('admin_manage_title', 'block_personality_test') . '</a></li>';
echo '<li class="breadcrumb-item active" aria-current="page">' . get_string('individual_results', 'block_personality_test') . '</li>';
echo '</ol>';
echo '</nav>';

echo "<div class='container-fluid'>";

// Header con información del estudiante
echo "<div class='row mb-4'>";
echo "<div class='col-12'>";
echo "<div class='card'>";
echo "<div class='card-header bg-primary text-white'>";
echo "<h3 class='card-title mb-0'>";
echo "<i class='fa fa-user'></i> " . get_string('individual_results', 'block_personality_test');
echo "</h3>";
echo "</div>";
echo "<div class='card-body'>";
echo "<div class='row'>";
echo "<div class='col-md-8'>";
echo "<h4>" . fullname($user) . "</h4>";
echo "<p class='text-muted mb-1'><strong>" . get_string('email', 'block_personality_test') . ":</strong> " . $user->email . "</p>";
echo "<p class='text-muted mb-1'><strong>" . get_string('test_date', 'block_personality_test') . ":</strong> " . date('d/m/Y H:i', $test_result->created_at) . "</p>";
echo "<p class='text-muted mb-1'><strong>" . get_string('mbti_type', 'block_personality_test') . ":</strong> <span class='badge bg-primary fs-6'>" . $mbti . "</span></p>";
echo "</div>";
echo "<div class='col-md-4 text-end'>";
echo "<a href='" . new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)) . "' class='btn btn-secondary'>";
echo "<i class='fa fa-arrow-left'></i> " . get_string('back_to_admin', 'block_personality_test');
echo "</a>";
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
echo "<div class='card-header'>";
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
    echo "<div class='progress-bar bg-info' role='progressbar' style='width: " . $percent1 . "%' aria-valuenow='" . $percent1 . "' aria-valuemin='0' aria-valuemax='100'>";
    echo round($percent1, 1) . "%";
    echo "</div>";
    echo "<div class='progress-bar bg-warning' role='progressbar' style='width: " . $percent2 . "%' aria-valuenow='" . $percent2 . "' aria-valuemin='0' aria-valuemax='100'>";
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
echo "<div class='card-header'>";
echo "<h5 class='mb-0'><i class='fa fa-info-circle'></i> " . get_string('summary_actions', 'block_personality_test') . "</h5>";
echo "</div>";
echo "<div class='card-body'>";

// Usar cadenas de idioma para las descripciones MBTI
$mbti_key = 'mbti_' . strtolower($mbti);
$mbti_dimensions_key = 'mbti_dimensions_' . strtolower($mbti);

echo "<div class='text-center mb-4'>";
echo "<h2 class='text-primary mb-2'>" . $mbti . "</h2>";
echo "<p class='text-muted' style='font-size: 0.9em;'>(" . get_string($mbti_dimensions_key, 'block_personality_test') . ")</p>";
echo "<p class='text-muted' style='text-align: justify;'>" . get_string($mbti_key, 'block_personality_test') . "</p>";
echo "</div>";

echo "<div class='d-flex justify-content-center gap-2'>";
echo "<a href='" . new moodle_url('/blocks/personality_test/download_pdf.php', 
        array('userid' => $userid, 'cid' => $courseid)) . 
        "' class='btn btn-success'>";
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
echo "<div class='card-header'>";
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
    ['E/I', $test_result->extraversion, $test_result->introversion, 'Extraversión', 'Introversión'],
    ['S/N', $test_result->sensing, $test_result->intuition, 'Sensación', 'Intuición'],
    ['T/F', $test_result->thinking, $test_result->feeling, 'Pensamiento', 'Sentimiento'],
    ['J/P', $test_result->judging, $test_result->perceptive, 'Juicio', 'Percepción']
];

foreach ($dimensions as $dim) {
    $dominant = $dim[1] >= $dim[2] ? $dim[3] : $dim[4];
    $score = max($dim[1], $dim[2]);
    echo "<tr>";
    echo "<td><strong>" . $dim[0] . "</strong></td>";
    echo "<td>" . $dim[1] . " / " . $dim[2] . "</td>";
    echo "<td><span class='badge bg-secondary'>" . $dominant . "</span></td>";
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

echo $OUTPUT->footer();
?>
