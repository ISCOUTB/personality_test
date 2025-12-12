<?php

require_once(dirname(__FILE__) . '/lib.php');

require_login();
require_sesskey();

/**
 * Merge existing answers from database record into data object
 * @param stdClass $data The data object to merge into
 * @param stdClass|null $existing_record The existing database record
 * @return stdClass The merged data object
 */
function merge_existing_answers($data, $existing_record) {
    if ($existing_record) {
        $data->id = $existing_record->id;
        $data->created_at = $existing_record->created_at;
        
        // Copy all existing answers from q1 to q72
        for ($i = 1; $i <= 72; $i++) {
            $field = "q{$i}";
            if (!isset($data->$field) && isset($existing_record->$field) && $existing_record->$field !== null) {
                $data->$field = $existing_record->$field;
            }
        }
    }
    return $data;
}

/**
 * Save or update personality test progress with race condition handling
 * @param stdClass $data The data to save
 * @param int $userid User ID
 * @return bool Success status
 * @throws Exception
 */
function save_test_progress($data, $userid) {
    global $DB;
    
    $current_record = $DB->get_record('personality_test', array('user' => $userid));
    
    if ($current_record) {
        $data = merge_existing_answers($data, $current_record);
        $DB->update_record('personality_test', $data);
    } else {
        try {
            $DB->insert_record('personality_test', $data);
        } catch (dml_exception $e) {
            // Race condition: another request inserted the record
            $current_record = $DB->get_record('personality_test', array('user' => $userid));
            if ($current_record) {
                $data = merge_existing_answers($data, $current_record);
                $DB->update_record('personality_test', $data);
            } else {
                throw $e;
            }
        }
    }
    return true;
}

/**
 * Prepare base data object for saving
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @param stdClass|null $existing_response Existing response if any
 * @return stdClass
 */
function prepare_data_object($userid, $courseid, $existing_response) {
    $data = new stdClass();
    $data->user = $userid;
    $data->course = $courseid;
    $data->state = 1;
    $data->is_completed = 0;
    $data->updated_at = time();
    
    if ($existing_response) {
        $data->id = $existing_response->id;
        $data->created_at = $existing_response->created_at;
        
        // Copy all existing answers
        for ($i = 1; $i <= 72; $i++) {
            $field = "q{$i}";
            if (isset($existing_response->$field) && $existing_response->$field !== null) {
                $data->$field = $existing_response->$field;
            }
        }
    } else {
        $data->created_at = time();
    }
    
    return $data;
}

$courseid = required_param('cid', PARAM_INT);
$action = optional_param('action', 'finish', PARAM_ALPHA); // 'autosave', 'previous', 'next', 'finish'
$page = optional_param('page', 1, PARAM_INT);

if ($courseid == SITEID && !$courseid) {
    if ($action === 'autosave') {
        echo json_encode(['success' => false, 'error' => 'Invalid course']);
        exit;
    }
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Check if user already completed the test
$existing_response = $DB->get_record('personality_test', array('user' => $USER->id));

if ($existing_response && $existing_response->is_completed && $action === 'finish') {
    $redirect_url = new moodle_url('/course/view.php', array('id' => $courseid));
    redirect($redirect_url, get_string('test_completed_redirect', 'block_personality_test'), null, \core\output\notification::NOTIFY_INFO);
}

// Collect all 72 responses
$responses = array();
$personality_test_a = array();
$all_answered = true;

for ($i = 1; $i <= 72; $i++) {
    // Use PARAM_RAW to distinguish between '0' (No) and '' (Unanswered)
    $response_raw = optional_param("personality_test:q" . $i, null, PARAM_RAW);
    
    if ($response_raw !== null && $response_raw !== '') {
        $response = (int)$response_raw;
        $personality_test_a[$i] = $response;
        $responses["q{$i}"] = $response;
    } else {
        $personality_test_a[$i] = null;
        $all_answered = false;
    }
}

// For autosave, allow partial data
if ($action === 'autosave') {
    $data = prepare_data_object($USER->id, $courseid, $existing_response);
    
    // Add/Update only answered questions from current page
    foreach ($responses as $field => $value) {
        $data->$field = $value;
    }
    
    try {
        save_test_progress($data, $USER->id);
        echo json_encode(['success' => true, 'answered' => count($responses)]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// For navigation (previous/next), save progress and redirect
if ($action === 'previous' || $action === 'next') {
    $data = prepare_data_object($USER->id, $courseid, $existing_response);
    
    // Update with new answers from current page
    foreach ($responses as $field => $value) {
        $data->$field = $value;
    }
    
    try {
        save_test_progress($data, $USER->id);
        
        // Calculate new page
        $new_page = ($action === 'previous') ? $page - 1 : $page + 1;
        $redirect_url = new moodle_url('/blocks/personality_test/view.php', array('cid' => $courseid, 'page' => $new_page));
        redirect($redirect_url, get_string('progress_saved', 'block_personality_test'), null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (Exception $e) {
        $redirect_url = new moodle_url('/blocks/personality_test/view.php', array('cid' => $courseid, 'page' => $page, 'error' => 1));
        redirect($redirect_url, 'Error: ' . $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
    exit;
}

// For finish, validate all questions are answered and calculate results
// SECURITY: Always validate ALL 72 questions are answered before finishing
if ($action === 'finish') {
    // Double check: validate from DB + current submission
    $existing = $DB->get_record('personality_test', array('user' => $USER->id));
    
    // Merge all answers (DB + current submission)
    for ($i = 1; $i <= 72; $i++) {
        $field = "q{$i}";
        
        // Use current submission if available
        if (!isset($responses[$field]) || $responses[$field] === null) {
            // Otherwise, try from DB
            if ($existing && isset($existing->$field) && $existing->$field !== null) {
                $responses[$field] = $existing->$field;
                $personality_test_a[$i] = $existing->$field;
            }
        }
    }
    
    // Find first unanswered question
    $first_unanswered = null;
    for ($i = 1; $i <= 72; $i++) {
        $field = "q{$i}";
        if (!isset($responses[$field]) || $responses[$field] === null) {
            $first_unanswered = $i;
            break;
        }
    }
    
    // If any question is unanswered, redirect to that page
    if ($first_unanswered !== null) {
        $redirect_page = ceil($first_unanswered / 9);
        $redirect_url = new moodle_url('/blocks/personality_test/view.php', 
                       array('cid' => $courseid, 'page' => $redirect_page));
        redirect($redirect_url, get_string('all_questions_required', 'block_personality_test'), 
                null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Calculate results
$extra = [5,7,10,13,23,25,61,68,71];
$intra = [2,9,49,54,63,65,67,69,72];
$sensi = [15,45,45,51,53,56,59,66,70];
$intui = [37,39,41,44,47,52,57,62,64];
$ratio = [1,4,6,18,20,48,50,55,58];
$emoti = [3,8,11,14,27,31,33,35,40];
$estru = [19,21,24,26,29,34,36,42,46];
$perce = [12,16,17,22,28,30,32,38,60];

$extra_res = 0;
$intra_res = 0;
$sensi_res = 0;
$intui_res = 0;
$ratio_res = 0;
$emoti_res = 0;
$estru_res = 0;
$perce_res = 0;

foreach($extra as $index => $value){
    $extra_res = $extra_res + $personality_test_a[$value];
}
foreach($intra as $index => $value){
    $intra_res = $intra_res + $personality_test_a[$value];
}
foreach($sensi as $index => $value){
    $sensi_res = $sensi_res + $personality_test_a[$value];
}
foreach($intui as $index => $value){
    $intui_res = $intui_res + $personality_test_a[$value];
}
foreach($ratio as $index => $value){
    $ratio_res = $ratio_res + $personality_test_a[$value];
}
foreach($emoti as $index => $value){
    $emoti_res = $emoti_res + $personality_test_a[$value];
}
foreach($estru as $index => $value){
    $estru_res = $estru_res + $personality_test_a[$value];
}
foreach($perce as $index => $value){
    $perce_res = $perce_res + $personality_test_a[$value];
}

// Save final results with is_completed = 1
if(save_personality_test($courseid,$extra_res,$intra_res,$sensi_res,$intui_res,$ratio_res,$emoti_res,$estru_res,$perce_res, $responses)){
    $redirect = new moodle_url('/course/view.php', array('id'=>$courseid));
    redirect($redirect, get_string('redirect_accept_success', 'block_personality_test') );
}else{
    $redirect = new moodle_url('/course/view.php', array('id'=>$courseid));
    redirect($redirect, get_string('redirect_accept_exist', 'block_personality_test') );
}
?>
