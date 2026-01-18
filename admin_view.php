<?php
/**
 * Admin Dashboard for Personality Test Block
 *
 * @package    block_personality_test
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/tablelib.php');

$courseid = optional_param('cid', 0, PARAM_INT); // Using 'cid' to be consistent with other files in this block
if (!$courseid) {
    print_error('missingparam', 'block_personality_test');
}

if ($courseid == SITEID) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = context_course::instance($courseid);
$PAGE->set_context($context);

require_login($course, false);

// Check if the block is added to the course
if (!$DB->record_exists('block_instances', array('blockname' => 'personality_test', 'parentcontextid' => $context->id))) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

// Friendly redirect for unauthorized users
if (!has_capability('block/personality_test:viewreports', $context)) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}

// Parameters
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$search = optional_param('search', '', PARAM_NOTAGS);

$admin_url = new moodle_url('/blocks/personality_test/admin_view.php', array('cid' => $courseid));
$PAGE->set_url($admin_url);

// Handle Delete Action
if ($action === 'delete' && $userid && confirm_sesskey()) {
    $confirm = optional_param('confirm', 0, PARAM_INT);
    if ($confirm) {
        // Privacy check
        $targetuser = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
        if (!is_enrolled($context, $targetuser, 'block/personality_test:taketest', true)
            || has_capability('block/personality_test:viewreports', $context, $userid)) {
             redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
        }
        
        $DB->delete_records('personality_test', array('user' => $userid));
        redirect($admin_url, get_string('participation_deleted', 'block_personality_test'));
    }
}

$title = get_string('admin_manage_title', 'block_personality_test');
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title . " : " . $course->fullname);
$PAGE->set_heading($title . " : " . $course->fullname);
$PAGE->requires->css('/blocks/personality_test/styles.css');

// Template Data Construction
$data = [
    'title' => $title,
    'icon_url' => (new moodle_url('/blocks/personality_test/pix/icon.svg'))->out(),
    'description' => format_text(get_string('admin_dashboard_description', 'block_personality_test'), FORMAT_HTML),
    'courseid' => $courseid,
    'admin_url' => $admin_url->out(false),
    'csv_url' => (new moodle_url('/blocks/personality_test/download_csv.php', ['courseid' => $courseid, 'sesskey' => sesskey()]))->out(false),
    'pdf_url' => (new moodle_url('/blocks/personality_test/download_pdf.php', ['courseid' => $courseid, 'sesskey' => sesskey()]))->out(false),
    'course_url' => (new moodle_url('/course/view.php', ['id' => $courseid]))->out(false),
    'search_term' => $search
];

// Handle Delete Confirmation
$user = $DB->get_record('user', array('id' => $userid), 'firstname, lastname');
if ($user && $action === 'delete' && !$confirm) {
    $data['delete_confirmation'] = true;
    $data['confirm_message'] = get_string('confirm_delete_message', 'block_personality_test', fullname($user));
    $data['confirm_url'] = (new moodle_url('/blocks/personality_test/admin_view.php', [
        'cid' => $courseid,
        'action' => 'delete',
        'userid' => $userid,
        'confirm' => 1,
        'sesskey' => sesskey()
    ]))->out(false);
    $data['cancel_url'] = $admin_url->out(false);
    
    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('block_personality_test/admin_view', $data);
    echo $OUTPUT->footer();
    exit;
}

// 1. Get Enrolled Users Helper
// We use 'block/personality_test:taketest' capability to filter students
list($esql, $params) = get_enrolled_sql($context, 'block/personality_test:taketest', 0, true);

// 2. Statistics (Count only)
$sql_enrolled = "SELECT COUNT(DISTINCT u.id) FROM {user} u JOIN ($esql) je ON je.id = u.id WHERE u.deleted = 0";
$total_students = $DB->count_records_sql($sql_enrolled, $params);

// SQL for completed/in-progress
$sql_completed_count = "SELECT COUNT(pt.id) FROM {personality_test} pt JOIN ($esql) je ON je.id = pt.user WHERE pt.is_completed = 1";
$completed_tests = $DB->count_records_sql($sql_completed_count, $params);

$sql_all_responses = "SELECT COUNT(pt.id) FROM {personality_test} pt JOIN ($esql) je ON je.id = pt.user";
$count_responses = $DB->count_records_sql($sql_all_responses, $params);
$in_progress_tests = $count_responses - $completed_tests;

$completion_rate = $total_students > 0 ? round(($completed_tests / $total_students) * 100, 1) : 0;

$data['total_students'] = $total_students;
$data['completed_tests'] = $completed_tests;
$data['in_progress_tests'] = $in_progress_tests;
$data['completion_rate'] = $completion_rate;
$data['has_completed'] = ($completed_tests > 0);

// 3. Stats Optimization
if ($completed_tests > 0) {
    // Averages
    $sql_avg = "SELECT 
            AVG(extraversion) as avg_extraversion,
            AVG(introversion) as avg_introversion,
            AVG(sensing) as avg_sensing,
            AVG(intuition) as avg_intuition,
            AVG(thinking) as avg_thinking,
            AVG(feeling) as avg_feeling,
            AVG(judging) as avg_judging,
            AVG(perceptive) as avg_perceptive
          FROM {personality_test} pt 
          JOIN ($esql) je ON je.id = pt.user 
          WHERE pt.is_completed = 1";
    
    $avgs = $DB->get_record_sql($sql_avg, $params);

    // Calculate Distribution for Charts (Colors from block_personality_test.php or charts.js palette)
    $helper_dim = function($avg1, $avg2, $color1, $color2, $l1, $l2) {
        $val1 = round($avg1, 1);
        $val2 = round($avg2, 1);
        return [
            'left_label' => $l1,
            'right_label' => $l2,
            'left_avg' => $val1,
            'right_avg' => $val2,
            'left_color' => $color1,
            'right_color' => $color2
        ];
    };
    
    // Using colors from the previous implementation / charts.js
    // Introversion (Blue) / Extraversion (Orange) -> Note: block_personality_test.php uses introversion as blueish
    $data['dimension_stats'] = [
        $helper_dim($avgs->avg_introversion, $avgs->avg_extraversion, '#005B9A', '#FF8200', get_string('Introvertido', 'block_personality_test'), get_string('Extrovertido', 'block_personality_test')),
        $helper_dim($avgs->avg_sensing, $avgs->avg_intuition, '#00B5E2', '#FFB600', get_string('Sensing', 'block_personality_test'), get_string('Intuicion', 'block_personality_test')),
        $helper_dim($avgs->avg_thinking, $avgs->avg_feeling, '#78BE20', '#652C8F', get_string('Pensamiento', 'block_personality_test'), get_string('Sentimiento', 'block_personality_test')),
        $helper_dim($avgs->avg_judging, $avgs->avg_perceptive, '#AA182C', '#0077C8', get_string('Juicio', 'block_personality_test'), get_string('Percepcion', 'block_personality_test'))
    ];

    // Most Common Types Calculation
    // We need to fetch all completed results to compute types correctly in PHP
    // Ideally we would do this in SQL but it varies per DB engine.
    $sql_all_completed = "SELECT pt.id, pt.extraversion, pt.introversion, pt.sensing, pt.intuition, pt.thinking, pt.feeling, pt.judging, pt.perceptive 
                          FROM {personality_test} pt 
                          JOIN ($esql) je ON je.id = pt.user 
                          WHERE pt.is_completed = 1";
    $all_completed = $DB->get_records_sql($sql_all_completed, $params);
    
    $type_counts = [];
    foreach ($all_completed as $row) {
        $type = '';
        $type .= ($row->extraversion > $row->introversion) ? 'E' : 'I';
        $type .= ($row->sensing > $row->intuition) ? 'S' : 'N';
        $type .= ($row->thinking >= $row->feeling) ? 'T' : 'F';
        $type .= ($row->judging > $row->perceptive) ? 'J' : 'P';
        
        if (!isset($type_counts[$type])) {
            $type_counts[$type] = 0;
        }
        $type_counts[$type]++;
    }
    arsort($type_counts);
    $top_4 = array_slice($type_counts, 0, 4);
    
    $formatted_top_types = [];
    $rank = 1;
    // Color palette for types
    $type_colors = [
        '#005B9A', '#FF8200', '#FFB600', '#00B5E2', 
        '#78BE20', '#2C5234', '#652C8F', '#91268F', 
        '#D0006F', '#AA182C', '#8B0304', '#E35205'
    ];
    $color_idx = 0;
    
    foreach ($top_4 as $t => $c) {
        $formatted_top_types[] = [
            'rank' => $rank++,
            'label' => $t,
            'count' => $c,
            'percentage' => round(($c / $completed_tests) * 100, 1),
            'color_hex' => $type_colors[$color_idx++ % count($type_colors)]
        ];
    }
    $data['top_types'] = $formatted_top_types;
}

// 4. Participants Table
$userfields = \core_user\fields::for_name()->with_userpic()->get_sql('u', false, '', '', false)->selects;
$where_search = "";
$search_params = [];

if (!empty($search)) {
    $where_search = " AND (" . $DB->sql_like('u.firstname', ':s1', false) . " OR " . $DB->sql_like('u.lastname', ':s2', false) . " OR " . $DB->sql_like('u.email', ':s3', false) . ")";
    $search_params = ['s1' => "%$search%", 's2' => "%$search%", 's3' => "%$search%"];
}

$sql_count_participants = "SELECT COUNT(pt.id) 
                           FROM {personality_test} pt
                           JOIN {user} u ON pt.user = u.id
                           JOIN ($esql) je ON je.id = pt.user
                           WHERE 1=1 $where_search";

$total_rows = $DB->count_records_sql($sql_count_participants, array_merge($params, $search_params));

$sql_list = "SELECT pt.*, {$userfields}
             FROM {personality_test} pt
             JOIN {user} u ON pt.user = u.id
             JOIN ($esql) je ON je.id = pt.user
             WHERE 1=1 $where_search
             ORDER BY pt.created_at DESC";

$participants = $DB->get_records_sql($sql_list, array_merge($params, $search_params), $page * $perpage, $perpage);

$data['show_table'] = ($count_responses > 0);

$list = [];
if ($participants) {
    foreach ($participants as $p) {
        $userpicture = new user_picture($p);
        $userpicture->size = 35;
        
        $row = [
            'userpicture' => $OUTPUT->render($userpicture),
            'fullname' => fullname($p),
            'email' => $p->email,
            'is_completed' => ($p->is_completed == 1),
            'created_at' => userdate($p->created_at, get_string('strftimedatetimeshort')),
            'view_url' => (new moodle_url('/blocks/personality_test/view_individual.php', ['cid' => $courseid, 'userid' => $p->user]))->out(false),
            'delete_url' => (new moodle_url('/blocks/personality_test/admin_view.php', ['cid' => $courseid, 'action' => 'delete', 'userid' => $p->user, 'sesskey' => sesskey()]))->out(false)
        ];
        
        if ($p->is_completed == 1) {
             $mbti = '';
             $mbti .= ($p->extraversion > $p->introversion) ? 'E' : 'I';
             $mbti .= ($p->sensing > $p->intuition) ? 'S' : 'N';
             $mbti .= ($p->thinking >= $p->feeling) ? 'T' : 'F';
             $mbti .= ($p->judging > $p->perceptive) ? 'J' : 'P';
             $row['mbti_type'] = $mbti;
        } else {
             $answered = 0;
             for ($i = 1; $i <= 72; $i++) {
                 $field = 'q' . $i;
                 if (isset($p->$field) && $p->$field !== null && $p->$field !== '') {
                     $answered++;
                 }
             }
             $row['answered'] = $answered;
        }
        
        $list[] = $row;
    }
}
$data['list'] = $list;

$baseurl = new moodle_url('/blocks/personality_test/admin_view.php', ['cid' => $courseid]);
if ($search) {
    $baseurl->param('search', $search);
}
$data['pagination'] = $OUTPUT->render(new paging_bar($total_rows, $page, $perpage, $baseurl, 'page'));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('block_personality_test/admin_view', $data);
echo $OUTPUT->footer();
