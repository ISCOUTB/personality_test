<?php
/**
 * Test View - Personality Test Block
 *
 * @package    block_personality_test
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/lib.php');

if( !isloggedin() ){
            return;
}

$courseid = required_param('cid', PARAM_INT);
$page = optional_param('page', 1, PARAM_INT);
$error  = optional_param('error', 0, PARAM_INT);
$scroll_to_finish = optional_param('scroll_to_finish', 0, PARAM_INT);

if ($courseid == SITEID && !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

require_login($course);

// Check if the block is added to the course
if (!$DB->record_exists('block_instances', array('blockname' => 'personality_test', 'parentcontextid' => $context->id))) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

// If a user with reporting capability tries to open the student test view, redirect them to admin silently
if (has_capability('block/personality_test:viewreports', $context) && !has_capability('block/personality_test:taketest', $context)) {
    redirect(new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid)), get_string('teachers_redirect_message', 'block_personality_test'), null, \core\output\notification::NOTIFY_INFO);
}

// Check for existing response
$existing_response = $DB->get_record('personality_test', array('user' => $USER->id));

// If test is completed, redirect to results
if ($existing_response && $existing_response->is_completed) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)), 
             get_string('test_completed_redirect', 'block_personality_test'), 
             null, \core\output\notification::NOTIFY_INFO);
}
  
$PAGE->set_url('/blocks/personality_test/view.php', array('cid'=>$courseid, 'page'=>$page));

$title = get_string('pluginname', 'block_personality_test');

$PAGE->set_pagelayout('incourse');
$PAGE->set_title($title." : ".$course->fullname);
$PAGE->set_heading($title." : ".$course->fullname);

$PAGE->requires->css(new moodle_url('/blocks/personality_test/styles.css'));

// Pagination settings
$questions_per_page = 9;
$total_questions = 72;
$total_pages = ceil($total_questions / $questions_per_page);

// SECURITY: Validate that user cannot skip pages without completing previous ones
if ($existing_response && $page > 1) {
    // Check all questions from page 1 to current page - 1
    $max_allowed_page = 1;
    
    for ($p = 1; $p < $page; $p++) {
        $page_start = ($p - 1) * $questions_per_page + 1;
        $page_end = min($p * $questions_per_page, $total_questions);
        $page_complete = true;
        
        for ($i = $page_start; $i <= $page_end; $i++) {
            $field = "q{$i}";
            if (!isset($existing_response->$field) || $existing_response->$field === null) {
                $page_complete = false;
                break;
            }
        }
        
        if ($page_complete) {
            $max_allowed_page = $p + 1;
        } else {
            break;
        }
    }
    
    // If trying to access a page beyond allowed, redirect to max allowed
    if ($page > $max_allowed_page) {
        redirect(new moodle_url('/blocks/personality_test/view.php', 
                 array('cid' => $courseid, 'page' => $max_allowed_page)));
    }
}

// If coming from "continue test" link, calculate which page to show
if ($existing_response && !isset($_GET['page'])) {
    // Find first unanswered question
    $first_unanswered = null;
    for ($i = 1; $i <= $total_questions; $i++) {
        $field = "q{$i}";
        if (!isset($existing_response->$field) || $existing_response->$field === null) {
            $first_unanswered = $i;
            break;
        }
    }
    
    // Calculate page for first unanswered question
    if ($first_unanswered !== null) {
        $page = ceil($first_unanswered / $questions_per_page);
    }
}

$start_question = ($page - 1) * $questions_per_page + 1;
$end_question = min($page * $questions_per_page, $total_questions);

// Calculate how many questions are answered
$answered_count = 0;
if ($existing_response) {
    for ($i = 1; $i <= $total_questions; $i++) {
        $field = "q{$i}";
        if (isset($existing_response->$field) && $existing_response->$field !== null) {
            $answered_count++;
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');

$questions = [];
for ($i = $start_question; $i <= $end_question; $i++) {
    $field = "q{$i}";
    $saved_value = ($existing_response && isset($existing_response->$field)) ? $existing_response->$field : null;
    
    $questions[] = [
        'number' => $i,
        'question_text' => get_string("personality_test:q".$i, 'block_personality_test'),
        'yes_label' => get_string('yes', 'block_personality_test'),
        'no_label' => get_string('no', 'block_personality_test'),
        'select_option_label' => get_string('select_option', 'block_personality_test'),
        'class_yes' => ($saved_value === '1' || $saved_value === 1) ? 'selected' : '',
        'class_no' => ($saved_value === '0' || $saved_value === 0) ? 'selected' : '',
        'selected_none' => ($saved_value === null) ? 'selected' : '',
        'selected_yes' => ($saved_value === '1' || $saved_value === 1) ? 'selected' : '',
        'selected_no' => ($saved_value === '0' || $saved_value === 0) ? 'selected' : '',
        'is_yes_selected_attr' => ($saved_value === '1' || $saved_value === 1) ? 'selected' : '',
        'is_no_selected_attr' => ($saved_value === '0' || $saved_value === 0) ? 'selected' : '',
    ];
}

$hidden_answers = [];
if ($existing_response) {
    for ($i = 1; $i <= 72; $i++) {
        if ($i >= $start_question && $i <= $end_question) {
            continue;
        }
        $field = "q{$i}";
        if (isset($existing_response->$field) && $existing_response->$field !== null) {
            $hidden_answers[] = [
                'number' => $i,
                'value' => $existing_response->$field
            ];
        }
    }
}

$should_auto_scroll = ($existing_response && $answered_count > 0 && $answered_count < 72 && !$scroll_to_finish);
$action_form = new moodle_url('/blocks/personality_test/save.php');

$template_data = [
    'iconurl' => (new moodle_url('/blocks/personality_test/pix/icon.svg'))->out(),
    'test_page_title' => get_string('test_page_title', 'block_personality_test'),
    'test_intro_p1' => get_string('test_intro_p1', 'block_personality_test'),
    'test_intro_p2' => get_string('test_intro_p2', 'block_personality_test'),
    'test_benefit_note' => get_string('test_benefit_note', 'block_personality_test'),
    'test_benefit_required' => get_string('test_benefit_required', 'block_personality_test'),
    'action_form' => $action_form->out(),
    'questions' => $questions,
    'hidden_answers' => $hidden_answers,
    'courseid' => $courseid,
    'page' => $page,
    'sesskey' => sesskey(),
    'show_previous' => ($page > 1),
    'show_next' => ($page < $total_pages),
    'show_finish' => ($page >= $total_pages),
    'btn_previous_label' => get_string('btn_previous', 'block_personality_test'),
    'btn_next_label' => get_string('btn_next', 'block_personality_test'),
    'btn_finish_label' => get_string('btn_finish', 'block_personality_test'),
    'wwwroot' => $CFG->wwwroot,
    'should_auto_scroll' => $should_auto_scroll,
    'scroll_to_finish' => $scroll_to_finish,
];

echo $OUTPUT->render_from_template('block_personality_test/test_view', $template_data);

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
