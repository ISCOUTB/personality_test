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
echo "<div class='block_personality_test_container'>";

echo "<h1 class='mb-4'>" . get_string('admin_manage_title', 'block_personality_test') . "</h1>";

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
    // Get enrolled students in this course
    $enrolled_students = get_enrolled_users($context, '', 0, 'u.id');
    $enrolled_ids = array_keys($enrolled_students);
    
    // Count participants who are enrolled in this course
    $total_participants = 0;
    if (!empty($enrolled_ids)) {
        list($insql, $params) = $DB->get_in_or_equal($enrolled_ids, SQL_PARAMS_NAMED);
        $total_participants = $DB->count_records_select('personality_test', "user $insql", $params);
    }
    
    echo "<div class='row mb-4'>";
    echo "<div class='col-md-6'>";
    echo "<div class='card'>";
    echo "<div class='card-body text-center'>";
    echo "<h5 class='card-title'>" . get_string('total_participants', 'block_personality_test') . "</h5>";
    echo "<h2 class='text-primary'>" . $total_participants . "</h2>";
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
        echo "<h5>" . get_string('no_participants', 'block_personality_test') . "</h5>";
        echo "<p>" . get_string('no_participants_message', 'block_personality_test') . "</p>";
        echo "</div>";
    } else {
        echo "<div class='card'>";
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
        echo "<th>" . get_string('mbti_type', 'block_personality_test') . "</th>";
        echo "<th>" . get_string('test_date', 'block_personality_test') . "</th>";
        echo "<th>" . get_string('actions', 'block_personality_test') . "</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        foreach ($participants as $participant) {
            // Calcular tipo MBTI
            $mbti = '';
            $mbti .= ($participant->extraversion >= $participant->introversion) ? 'E' : 'I';
            $mbti .= ($participant->sensing > $participant->intuition) ? 'S' : 'N';
            $mbti .= ($participant->thinking >= $participant->feeling) ? 'T' : 'F';
            $mbti .= ($participant->judging > $participant->perceptive) ? 'J' : 'P';

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
            echo "<td><span class='badge bg-primary'>" . $mbti . "</span></td>";
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
