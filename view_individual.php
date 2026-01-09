<?php
/**
 * Individual View - Personality Test Block
 *
 * @package    block_personality_test
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

require_login();

$userid = required_param('userid', PARAM_INT);
$courseid = required_param('cid', PARAM_INT);

// Verificar permisos: solo administradores y profesores
$isadmin = is_siteadmin($USER);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Check if the block is added to the course
if (!$DB->record_exists('block_instances', array('blockname' => 'personality_test', 'parentcontextid' => $context->id))) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

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

$template_data = [];

// Verificar si el test está completado
if ($test_result->is_completed == 0) {
    // Calcular progreso
    $answered = 0;
    for ($i = 1; $i <= 72; $i++) {
        $field = 'q' . $i;
        if (isset($test_result->$field) && $test_result->$field !== null && $test_result->$field !== '') {
            $answered++;
        }
    }
    
    $progress_percentage = round(($answered / 72) * 100, 1);
    
    $template_data = [
        'in_progress' => true,
        'is_completed' => false,
        'test_in_progress_title' => get_string('test_in_progress', 'block_personality_test'),
        'test_in_progress_message' => get_string('test_in_progress_message', 'block_personality_test', fullname($user)),
        'progress_label' => get_string('progress_label', 'block_personality_test'),
        'progress_percentage' => $progress_percentage,
        'has_answered_label' => get_string('has_answered', 'block_personality_test'),
        'answered_message' => get_string('of_72_questions', 'block_personality_test', $answered),
        'show_submit_reminder' => ($answered == 72),
        'remind_submit_test' => get_string('remind_submit_test', 'block_personality_test'),
        'results_available_message' => get_string('results_available_when_complete', 'block_personality_test', fullname($user)),
        'back_url' => (new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)))->out(),
        'back_to_admin_label' => get_string('back_to_admin', 'block_personality_test')
    ];
    
} else {
    // Si llegamos aquí, el test está completado, preparar datos para resultados completos
    // Calcular tipo MBTI
    $mbti = '';
    $mbti .= ($test_result->extraversion > $test_result->introversion) ? 'E' : 'I';
    $mbti .= ($test_result->sensing > $test_result->intuition) ? 'S' : 'N';
    $mbti .= ($test_result->thinking >= $test_result->feeling) ? 'T' : 'F';
    $mbti .= ($test_result->judging > $test_result->perceptive) ? 'J' : 'P';

    $mbti_key = 'mbti_' . strtolower($mbti);
    $mbti_dimensions_key = 'mbti_dimensions_' . strtolower($mbti);

    // Preparar dimension bars
    $dimension_bars = [];
    $raw_dimensions = [
        [get_string('extraversion', 'block_personality_test'), $test_result->extraversion, get_string('introversion', 'block_personality_test'), $test_result->introversion],
        [get_string('sensing', 'block_personality_test'), $test_result->sensing, get_string('intuition', 'block_personality_test'), $test_result->intuition],
        [get_string('thinking', 'block_personality_test'), $test_result->thinking, get_string('feeling', 'block_personality_test'), $test_result->feeling],
        [get_string('judging', 'block_personality_test'), $test_result->judging, get_string('perceptive', 'block_personality_test'), $test_result->perceptive]
    ];

    foreach ($raw_dimensions as $dim) {
        $total = $dim[1] + $dim[3];
        $percent1 = $total > 0 ? ($dim[1] / $total) * 100 : 50;
        $percent2 = $total > 0 ? ($dim[3] / $total) * 100 : 50;
        
        $dimension_bars[] = [
            'label1' => $dim[0],
            'value1' => $dim[1],
            'label2' => $dim[2],
            'value2' => $dim[3],
            'percent1' => $percent1,
            'percent2' => $percent2,
            'percent1_display' => round($percent1, 1),
            'percent2_display' => round($percent2, 1)
        ];
    }
    
    // Preparar score rows
    $score_rows = [];
    foreach ($raw_dimensions as $dim) {
        $dominant = ($dim[1] >= $dim[3]) ? $dim[0] : $dim[2];
        $score_rows[] = [
            'label1' => $dim[0],
            'value1' => $dim[1],
            'label2' => $dim[2],
            'value2' => $dim[3],
            'dominant' => $dominant
        ];
    }

    $template_data = [
        'in_progress' => false,
        'is_completed' => true,
        'iconurl' => (new moodle_url('/blocks/personality_test/pix/icon.svg'))->out(),
        'individual_results_title' => get_string('individual_results', 'block_personality_test'),
        'user_fullname' => fullname($user),
        'email_label' => get_string('email', 'block_personality_test'),
        'user_email' => $user->email,
        'date_label' => get_string('test_date', 'block_personality_test'),
        'test_date' => date('d/m/Y H:i', $test_result->created_at),
        'mbti_label' => get_string('mbti_type', 'block_personality_test'),
        'mbti_type' => $mbti,
        'dimensions_title' => get_string('personality_dimensions', 'block_personality_test'),
        'dimension_bars' => $dimension_bars,
        'summary_actions_title' => get_string('summary_actions', 'block_personality_test'),
        'mbti_dimensions_str' => get_string($mbti_dimensions_key, 'block_personality_test'),
        'mbti_description' => get_string($mbti_key, 'block_personality_test'),
        'download_url' => (new moodle_url('/blocks/personality_test/download_pdf.php', array('userid' => $userid, 'cid' => $courseid)))->out(false),
        'download_pdf_label' => get_string('download_pdf', 'block_personality_test'),
        'delete_url' => (new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid, 'action' => 'delete', 'userid' => $userid, 'sesskey' => sesskey())))->out(false),
        'confirm_delete_msg' => get_string('confirm_delete_individual', 'block_personality_test'),
        'delete_results_label' => get_string('delete_results', 'block_personality_test'),
        'detailed_scores_title' => get_string('detailed_scores', 'block_personality_test'),
        'dimension_header' => get_string('dimension', 'block_personality_test'),
        'score_header' => get_string('score', 'block_personality_test'),
        'preference_header' => get_string('preference', 'block_personality_test'),
        'score_rows' => $score_rows,
        'back_to_admin_url' => (new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)))->out(false),
        'back_to_admin_label' => get_string('back_to_admin', 'block_personality_test'),
        'back_to_course_url' => (new moodle_url('/course/view.php', array('id' => $courseid)))->out(false),
        'back_to_course_label' => get_string('back_to_course', 'block_personality_test')
    ];
}

echo $OUTPUT->render_from_template('block_personality_test/individual_view', $template_data);

echo $OUTPUT->footer();
?>
