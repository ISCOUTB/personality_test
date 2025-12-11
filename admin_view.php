<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_login();

$courseid = optional_param('cid', 0, PARAM_INT);

if ($courseid == SITEID && !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

// Verificar permisos: solo administradores y profesores
$isadmin = is_siteadmin($USER);
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

$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

// Procesar acciones
if ($action === 'delete' && $userid && confirm_sesskey()) {
    $confirm = optional_param('confirm', 0, PARAM_INT);
    if ($confirm) {
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

echo $OUTPUT->header();

// CSS personalizado
echo "<link rel='stylesheet' href='" . $CFG->wwwroot . "/blocks/personality_test/styles.css'>";
echo "<style>
    .block_personality_test_container h1 {
        color: #17a2b8;
        border-bottom: 3px solid #17a2b8;
        padding-bottom: 10px;
        display: inline-block;
    }
    .card {
        border: 1px solid #e3f2fd;
        box-shadow: 0 2px 4px rgba(23, 162, 184, 0.1);
        transition: all 0.3s ease;
    }
    .card:hover {
        box-shadow: 0 4px 8px rgba(23, 162, 184, 0.2);
        transform: translateY(-2px);
    }
    .card-header {
        background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
        border-bottom: 2px solid #17a2b8;
    }
    .card-header h5 {
        color: #17a2b8;
    }
    .card-title {
        color: #5a6268;
        font-weight: 500;
    }
    .table thead th {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        border: none;
    }
    .btn-info {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        border: none;
    }
    .btn-info:hover {
        background: linear-gradient(135deg, #138496 0%, #117a8b 100%);
    }
    .btn-primary {
        background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        border: none;
    }
    .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
        border: none;
    }
    .badge.bg-primary {
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%) !important;
    }
    .text-primary {
        color: #17a2b8 !important;
    }
    .alert-info {
        background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
        border-left: 4px solid #17a2b8;
    }
</style>";
echo "<div class='block_personality_test_container'>";

echo "<h1 class='mb-4'><i class='fa fa-users'></i> " . get_string('admin_manage_title', 'block_personality_test') . "</h1>";

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
                "' class='btn btn-danger'>" . get_string('confirm_delete_yes', 'block_personality_test') . "</a> ";
        echo "<a href='" . new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)) . 
                "' class='btn btn-secondary'>" . get_string('cancel', 'block_personality_test') . "</a>";
        echo "</div>";
        echo "</div>";
    }
} else {
    // Obtener estadísticas
    // Get enrolled students in this course (only students, roleid = 5)
    $enrolled_students = get_role_users(5, $context, false, 'u.id, u.firstname, u.lastname');
    $enrolled_ids = array_keys($enrolled_students);
    
    // Total students in course
    $total_students = count($enrolled_students);
    
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
    echo "<div class='col-md-4'>";
    echo "<div class='card border-info'>";
    echo "<div class='card-body text-center'>";
    echo "<i class='fa fa-users text-info' style='font-size: 2em; margin-bottom: 10px;'></i>";
    echo "<h5 class='card-title'>" . get_string('total_students', 'block_personality_test') . "</h5>";
    echo "<h2 class='text-info'>" . $total_students . "</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Completed tests card
    echo "<div class='col-md-4'>";
    echo "<div class='card border-success'>";
    echo "<div class='card-body text-center'>";
    echo "<i class='fa fa-check-circle text-success' style='font-size: 2em; margin-bottom: 10px;'></i>";
    echo "<h5 class='card-title'>" . get_string('completed_tests', 'block_personality_test') . "</h5>";
    echo "<h2 class='text-success'>" . $completed_tests . "</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // In progress tests card
    echo "<div class='col-md-4'>";
    echo "<div class='card border-warning'>";
    echo "<div class='card-body text-center'>";
    echo "<i class='fa fa-clock-o text-warning' style='font-size: 2em; margin-bottom: 10px;'></i>";
    echo "<h5 class='card-title'>" . get_string('in_progress_tests', 'block_personality_test') . "</h5>";
    echo "<h2 class='text-warning'>" . $in_progress_tests . "</h2>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    echo "</div>";

    // Obtener participantes con información del usuario
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

    if (empty($participants)) {
        echo "<div class='alert alert-info'>";
        echo "<i class='fa fa-info-circle'></i> ";
        echo "<h5>" . get_string('no_participants', 'block_personality_test') . "</h5>";
        echo "<p>" . get_string('no_participants_message', 'block_personality_test') . "</p>";
        echo "</div>";
    } else {
        echo "<div class='card'>";
        echo "<div class='card-header'>";
        echo "<h5 class='mb-0 d-inline'>" . get_string('participants_list', 'block_personality_test') . "</h5>";
        echo "</div>";
        echo "<div class='card-body'>";
        
        // Filtros y búsqueda
        echo "<div class='row mb-3'>";
        echo "<div class='col-md-6'>";
        echo "<input type='text' id='searchInput' class='form-control' placeholder='" . 
             get_string('search_participant', 'block_personality_test') . "'>";
        echo "</div>";
        echo "<div class='col-md-6'>";
        echo "<button class='btn btn-primary' onclick='exportData(\"csv\")'>" . 
             get_string('export_csv', 'block_personality_test') . "</button> ";
        echo "<button class='btn btn-success' onclick='exportData(\"pdf\")'>" . 
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
                echo "<span class='badge bg-success'>" . get_string('completed_status', 'block_personality_test') . "</span>";
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
                echo "<span class='badge bg-primary'>" . $mbti . "</span>";
            } else {
                echo "<span class='text-muted'>-</span>";
            }
            echo "</td>";
            
            echo "<td>" . date('d/m/Y H:i', $participant->created_at) . "</td>";
            echo "<td>";
            echo "<a href='" . new moodle_url('/blocks/personality_test/view_individual.php', 
                    array('userid' => $participant->user, 'cid' => $courseid)) . 
                    "' class='btn btn-sm btn-info me-1' title='" . get_string('view_results', 'block_personality_test') . "'>";
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
echo "<div class='mt-4'>";
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
