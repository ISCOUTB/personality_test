<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_login();

$courseid = optional_param('cid', 0, PARAM_INT);

if ($courseid == SITEID || !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = context_course::instance($courseid);
$PAGE->set_context($context);

require_login($course, false);

// Silent redirect for users without report capability (e.g., students)
if (!has_capability('block/personality_test:viewreports', $context)) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

// Procesar acciones
if ($action === 'delete' && $userid && confirm_sesskey()) {
    $confirm = optional_param('confirm', 0, PARAM_INT);
    if ($confirm) {
        // Defensive: allow deletion only for users that are students in this course.
        $targetuser = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
        if (!is_siteadmin() && (
            !is_enrolled($context, $targetuser, 'block/personality_test:taketest', true)
            || has_capability('block/personality_test:viewreports', $context, $userid)
            || is_siteadmin($userid)
        )) {
            redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
        }
        // Eliminar registro global del test del usuario
        $DB->delete_records('personality_test', array('user' => $userid));
        redirect(new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)), 
                 get_string('participation_deleted', 'block_personality_test'));
    }
}

$PAGE->set_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid));
$title = get_string('admin_manage_title', 'block_personality_test');
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title . " : " . $course->fullname);
$PAGE->set_heading($title . " : " . $course->fullname);

$PAGE->requires->css(new moodle_url('/blocks/personality_test/styles.css'));

echo $OUTPUT->header();
echo "<div class='block_personality_test_container'>";

// Generate personality test icon for header
$iconurl = new moodle_url('/blocks/personality_test/pix/personality_test_icon.svg');
$icon_html = '<img src="' . $iconurl . '" alt="Personality Test Icon" style="width: 50px; height: 50px; vertical-align: middle; margin-right: 15px;" />';

echo "<h1 class='mb-4 text-center'>" . $icon_html . get_string('admin_manage_title', 'block_personality_test') . "</h1>";

// Confirmación de eliminación
if ($action === 'delete' && $userid) {
    $user = $DB->get_record('user', array('id' => $userid), 'firstname, lastname');
    if ($user) {
        echo "<div class='alert alert-warning'>";
        echo "<h4>" . get_string('confirm_delete', 'block_personality_test') . "</h4>";
        echo "<p>" . get_string('confirm_delete_message', 'block_personality_test', fullname($user)) . "</p>";
        echo "<div class='mt-3'>";
        echo "<a href='" . new moodle_url('/blocks/personality_test/admin_view.php', 
                array('cid' => $courseid, 'action' => 'delete', 'userid' => $userid, 'confirm' => 1, 'sesskey' => sesskey())) . 
                "' class='btn btn-danger text-white'>" . get_string('confirm_delete_yes', 'block_personality_test') . "</a> ";
        echo "<a href='" . new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)) . 
                "' class='btn btn-secondary text-white'>" . get_string('cancel', 'block_personality_test') . "</a>";
        echo "</div>";
        echo "</div>";
    }
} else {
    // Description banner (match other admin dashboards)
    echo "<div class='alert alert-info mb-4'>";
    echo format_text(get_string('admin_dashboard_description', 'block_personality_test'), FORMAT_HTML);
    echo "</div>";

    // Obtener estadísticas
    // Get enrolled users who can take the test (defaults to student archetype)
    $enrolled_students = get_enrolled_users($context, 'block/personality_test:taketest', 0, 'u.id, u.firstname, u.lastname');
    $enrolled_ids = array_keys($enrolled_students);

    // Defensive: ensure only students show in admin tables (exclude report-capable users).
    $student_ids = array();
    foreach ($enrolled_ids as $candidateid) {
        $candidateid = (int)$candidateid;
        if (is_siteadmin($candidateid)) {
            continue;
        }
        if (has_capability('block/personality_test:viewreports', $context, $candidateid)) {
            continue;
        }
        $student_ids[] = $candidateid;
    }
    $enrolled_ids = $student_ids;
    
    // Total students in course
    $total_students = count($enrolled_ids);
    
    // Obtener participantes con información del usuario PRIMERO (antes de calcular estadísticas)
    $userfields = \core_user\fields::for_name()->with_userpic()->get_sql('u', false, '', '', false)->selects;
    $participants = array();
    if (!empty($enrolled_ids)) {
        list($insql, $params) = $DB->get_in_or_equal($enrolled_ids, SQL_PARAMS_NAMED);
        $sql = "SELECT pt.*, {$userfields}
                FROM {personality_test} pt
                JOIN {user} u ON pt.user = u.id
                WHERE pt.user $insql
                ORDER BY pt.created_at DESC";
        
        $participants = $DB->get_records_sql($sql, $params);
    }
    
    // Count participants who are enrolled in this course
    $completed_tests = 0;
    $in_progress_tests = 0;
    if (!empty($enrolled_ids)) {
        list($insql, $params) = $DB->get_in_or_equal($enrolled_ids, SQL_PARAMS_NAMED);
        
        // Count completed tests
        $params_completed = $params;
        $params_completed['completed'] = 1;
        $completed_tests = $DB->count_records_select('personality_test', "user $insql AND is_completed = :completed", $params_completed);
        
        // Count in-progress tests
        $params_progress = $params;
        $params_progress['completed'] = 0;
        $in_progress_tests = $DB->count_records_select('personality_test', "user $insql AND is_completed = :completed", $params_progress);
    }
    
    echo "<div class='row mb-4'>";
    
    // Total students card
    echo "<div class='col-md-3 mb-4'>";
    echo "<div class='card border-info' style='border-color: #00bf91 !important; border-radius: 4px !important;'>";
    echo "<div class='card-body text-center'>";
    echo "<i class='fa fa-users' style='font-size: 2em; margin-bottom: 10px; color: #00bf91;'></i>";
    echo "<h5 class='card-title'>" . get_string('total_students', 'block_personality_test') . "</h5>";
    echo "<h2 class='oficial-color'>" . $total_students . "</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Completed tests card
    echo "<div class='col-md-3 mb-4'>";
    echo "<div class='card border-success' style='border-color: #28a745 !important;'>";
    echo "<div class='card-body text-center'>";
    echo "<i class='fa fa-check-circle text-success' style='font-size: 2em; margin-bottom: 10px;'></i>";
    echo "<h5 class='card-title'>" . get_string('completed_tests', 'block_personality_test') . "</h5>";
    echo "<h2 class='text-success'>" . $completed_tests . "</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // In progress tests card
    echo "<div class='col-md-3 mb-4'>";
    echo "<div class='card border-warning' style='border-color: #ffc107 !important;'>";
    echo "<div class='card-body text-center'>";
    echo "<i class='fa fa-clock-o text-warning' style='font-size: 2em; margin-bottom: 10px;'></i>";
    echo "<h5 class='card-title'>" . get_string('in_progress_tests', 'block_personality_test') . "</h5>";
    echo "<h2 class='text-warning'>" . $in_progress_tests . "</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Completion rate card
    $completion_rate = $total_students > 0 ? round(($completed_tests / $total_students) * 100, 1) : 0;
    echo "<div class='col-md-3'>";
    echo "<div class='card border-primary' style='border-color: #00bf91 !important;'>";   
    echo "<div class='card-body text-center'>"; 
    echo '<i class="fa fa-percent text-primary" style="font-size: 2em; margin-bottom: 10px;"></i>';
    echo "<h5 class='card-title'>" . get_string('completion_rate', 'block_personality_test') . "</h5>";
    echo "<h2 class='text-primary'>" . $completion_rate . "%</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    echo "</div>";

    // General statistics section (only meaningful when there are completed tests)
    if ($completed_tests > 0) {
        echo "<div class='row mt-4'>";
        echo "<div class='col-12'>";
        echo "<div class='card'>";
        echo "<div class='card-header'>";
        echo "<h5 class='mb-0'><i class='fa fa-chart-bar'></i> " . get_string('general_statistics', 'block_personality_test') . "</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        echo "<div class='row'>";
        
        // Calculate MBTI type distribution
        $type_distribution = array();
        if (!empty($participants)) {
            foreach ($participants as $p) {
                if ($p->is_completed == 1) {
                    $e_or_i = $p->extraversion > $p->introversion ? 'E' : 'I';
                    $s_or_n = $p->sensing > $p->intuition ? 'S' : 'N';
                    $t_or_f = $p->thinking >= $p->feeling ? 'T' : 'F';
                    $j_or_p = $p->judging > $p->perceptive ? 'J' : 'P';
                    $type = $e_or_i . $s_or_n . $t_or_f . $j_or_p;
                    if (!isset($type_distribution[$type])) {
                        $type_distribution[$type] = 0;
                    }
                    $type_distribution[$type]++;
                }
            }
        }
        
        // Display top 4 most common types
        arsort($type_distribution);
        $top_types = array_slice($type_distribution, 0, 4, true);
        
        echo "<div class='col-md-6 mt-3 mt-md-0'>";
        echo "<h6 class='text-center text-md-left mt-3 mb-3 mt-md-0 mb-md-2'>" . get_string('most_common_types', 'block_personality_test') . "</h6>";
        if (!empty($top_types)) {
            echo "<ul class='list-group'>";
            foreach ($top_types as $type => $count) {
                $percentage = $completed_tests > 0 ? round(($count / $completed_tests) * 100, 1) : 0;
                echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                echo "<strong>" . $type . "</strong>";
                echo "<span class='badge bg-secondary rounded-pill'>" . $count . " (" . $percentage . "%)</span>";
                echo "</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='text-muted'>" . get_string('no_data_available', 'block_personality_test') . "</p>";
        }
        echo "</div>";
        
        // Average dimension scores
        echo "<div class='col-md-6 mt-3 mt-md-0'>";
        echo "<h6 class='text-center text-md-left mt-3 mb-3 mt-md-0 mb-md-2'>" . get_string('average_dimensions', 'block_personality_test') . "</h6>";
        if ($completed_tests > 0) {
            $avg_extraversion = 0;
            $avg_introversion = 0;
            $avg_sensing = 0;
            $avg_intuition = 0;
            $avg_thinking = 0;
            $avg_feeling = 0;
            $avg_judging = 0;
            $avg_perceptive = 0;
            
            foreach ($participants as $p) {
                if ($p->is_completed == 1) {
                    $avg_extraversion += $p->extraversion;
                    $avg_introversion += $p->introversion;
                    $avg_sensing += $p->sensing;
                    $avg_intuition += $p->intuition;
                    $avg_thinking += $p->thinking;
                    $avg_feeling += $p->feeling;
                    $avg_judging += $p->judging;
                    $avg_perceptive += $p->perceptive;
                }
            }
            
            $avg_extraversion = round($avg_extraversion / $completed_tests, 1);
            $avg_introversion = round($avg_introversion / $completed_tests, 1);
            $avg_sensing = round($avg_sensing / $completed_tests, 1);
            $avg_intuition = round($avg_intuition / $completed_tests, 1);
            $avg_thinking = round($avg_thinking / $completed_tests, 1);
            $avg_feeling = round($avg_feeling / $completed_tests, 1);
            $avg_judging = round($avg_judging / $completed_tests, 1);
            $avg_perceptive = round($avg_perceptive / $completed_tests, 1);
            
            echo "<ul class='list-group'>";
            echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
            echo "<strong>" . get_string('extraversion','block_personality_test') . " - " . get_string('introversion','block_personality_test') . "</strong>";
            echo "<span class='rounded-pill'>" . $avg_extraversion . " - " . $avg_introversion . "</span>";
            echo "</li>";
            echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
            echo "<strong>" . get_string('sensing','block_personality_test') . " - " . get_string('intuition','block_personality_test') . "</strong>";
            echo "<span class='rounded-pill'>" . $avg_sensing . " - " . $avg_intuition . "</span>";
            echo "</li>";
            echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
            echo "<strong>" . get_string('thinking','block_personality_test') . " - " . get_string('feeling','block_personality_test') . "</strong>";
            echo "<span class='rounded-pill'>" . $avg_thinking . " - " . $avg_feeling . "</span>";
            echo "</li>";
            echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
            echo "<strong>" . get_string('judging','block_personality_test') . " - " . get_string('perceptive','block_personality_test') . "</strong>";
            echo "<span class='rounded-pill'>" . $avg_judging . " - " . $avg_perceptive . "</span>";
            echo "</li>";
            echo "</ul>";
        } else {
            echo "<p class='text-muted'>" . get_string('no_data_available', 'block_personality_test') . "</p>";
        } 

        echo "</div>";
        
        echo "</div>"; // Cierra row de estadísticas
        echo "</div>"; // Cierra card-body
        echo "</div>"; // Cierra card
        echo "</div>"; // Cierra col-12
        echo "</div>"; // Cierra row mt-4
    }

    // Sección de Participants List
    if (empty($participants)) {
        echo "<div class='alert alert-info mt-4'>";
        echo "<i class='fa fa-info-circle'></i> ";
        echo "<h5>" . get_string('no_participants', 'block_personality_test') . "</h5>";
        echo "<p>" . get_string('no_participants_message', 'block_personality_test') . "</p>";
        echo "</div>";
    } else {
        echo "<div class='card mt-5'>";
        echo "<div class='card-header'>";
        echo "<h5 class='mb-0'>" . get_string('participants_list', 'block_personality_test') . "</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        // Filtros y búsqueda
        echo "<div class='row mb-3'>";
        echo "<div class='col-md-6'>";
        echo "<input type='text' id='searchInput' class='form-control' placeholder='" . 
             get_string('search_participant', 'block_personality_test') . "'>";
        echo "</div>";
        echo "<div class='col-md-6 d-flex justify-content-center justify-content-md-start mt-3 mt-md-0'>";
        echo "<button class='btn btn-primary mr-2' onclick='exportData(\"csv\")'><i class='fa fa-download mr-2'></i>" . 
             get_string('export_csv', 'block_personality_test') . "</button> ";
        echo "<button class='btn btn-success' onclick='exportData(\"pdf\")'><i class='fa fa-file-pdf-o mr-2'></i>" . 
             get_string('export_pdf', 'block_personality_test') . "</button>";
        echo "</div>";
        echo "</div>";

        // Tabla de participantes
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped table-hover' id='participantsTable'>";
        echo "<thead class='table-dark'>";
        echo "<tr>";
        echo "<th>" . get_string('student_name', 'block_personality_test') . "</th>";
        echo "<th>" . get_string('email', 'block_personality_test') . "</th>";
        echo "<th>" . get_string('status', 'block_personality_test') . "</th>";
        echo "<th>" . get_string('mbti_type', 'block_personality_test') . "</th>";
        echo "<th>" . get_string('test_date', 'block_personality_test') . "</th>";
        echo "<th>" . get_string('actions', 'block_personality_test') . "</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        foreach ($participants as $participant) {
            echo "<tr class='participant-row'>";
            echo "<td>";
            echo "<div class='d-flex align-items-center'>";
            $userpicture = new user_picture($participant);
            $userpicture->size = 35;
            echo $OUTPUT->render($userpicture);
            echo "<span class='ms-2'><strong>" . fullname($participant) . "</strong></span>";
            echo "</div>";
            echo "</td>";
            echo "<td>" . $participant->email . "</td>";
            
            // Estado y Progreso
            echo "<td>";
            if ($participant->is_completed == 1) {
                echo "<span class='badge bg-success text-white'>" . get_string('completed_status', 'block_personality_test') . "</span>";
            } else {
                // Calcular progreso - contar respuestas no nulas
                $answered = 0;
                for ($i = 1; $i <= 72; $i++) {
                    $field = 'q' . $i;
                    if (isset($participant->$field) && $participant->$field !== null && $participant->$field !== '') {
                        $answered++;
                    }
                }
                echo "<span class='badge bg-warning text-dark'>" . get_string('in_progress_status', 'block_personality_test') . "</span>";
                echo "<br><small class='text-muted'>" . get_string('of_72_questions', 'block_personality_test', $answered) . "</small>";
            }
            echo "</td>";
            
            // Tipo MBTI (solo si está completado)
            echo "<td>";
            if ($participant->is_completed == 1) {
                $mbti = '';
                $mbti .= ($participant->extraversion >= $participant->introversion) ? 'E' : 'I';
                $mbti .= ($participant->sensing > $participant->intuition) ? 'S' : 'N';
                $mbti .= ($participant->thinking >= $participant->feeling) ? 'T' : 'F';
                $mbti .= ($participant->judging > $participant->perceptive) ? 'J' : 'P';
                echo "<strong>" . $mbti . "</strong>";
            } else {
                echo "<span class='text-muted'>-</span>";
            }
            echo "</td>";
            
            echo "<td>" . date('d/m/Y H:i', $participant->created_at) . "</td>";
            echo "<td>";
            echo "<a href='" . new moodle_url('/blocks/personality_test/view_individual.php', 
                    array('userid' => $participant->user, 'cid' => $courseid)) . 
                    "' class='btn btn-sm btn-info mr-2 mt-1 mb-1' title='" . get_string('view_results', 'block_personality_test') . "'>";
            echo "<i class='fa fa-eye'></i> " . get_string('view', 'block_personality_test');
            echo "</a>";
            echo "<a href='" . new moodle_url('/blocks/personality_test/admin_view.php', 
                    array('cid' => $courseid, 'action' => 'delete', 'userid' => $participant->user, 'sesskey' => sesskey())) . 
                    "' class='btn btn-sm btn-danger' title='" . get_string('delete_participation', 'block_personality_test') . "'>";
            echo "<i class='fa fa-trash'></i> " . get_string('delete', 'block_personality_test');
            echo "</a>";
            echo "</td>";
            echo "</tr>";
        }

        echo "</tbody>";
        echo "</table>";
        echo "</div>";
        echo "</div>";
        echo "</div>";
    }
}

// Botón para regresar al curso
echo "<div class='mt-4 text-center'>";
echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary'>";
echo "<i class='fa fa-arrow-left'></i> " . get_string('back_to_course', 'block_personality_test');
echo "</a>";
echo "</div>";

echo "</div>";

// JavaScript para funcionalidad
echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    
    function filterTable() {
        const filter = searchInput.value.toLowerCase();
        const rows = document.querySelectorAll('#participantsTable .participant-row');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matchesSearch = text.includes(filter);
            row.style.display = matchesSearch ? '' : 'none';
        });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }
    
    // Función para exportar datos
    window.exportData = function(format) {
        if (format === 'csv') {
            window.location.href = '" . $CFG->wwwroot . "/blocks/personality_test/download_csv.php?cid=" . $courseid . "';
        } else if (format === 'pdf') {
            window.location.href = '" . $CFG->wwwroot . "/blocks/personality_test/download_pdf.php?cid=" . $courseid . "';
        }
    };
});
</script>";

echo $OUTPUT->footer();
?>
