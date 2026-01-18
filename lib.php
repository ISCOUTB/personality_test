<?php 
/**
 * Personality Test Library Functions
 *
 * @package    block_personality_test
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');

function save_personality_test($course,$extra_res,$intra_res,$sensi_res,$intui_res,$ratio_res,$emoti_res,$estru_res,$perce_res, $responses = array()) {
    GLOBAL $DB, $USER, $CFG;
    // Check if user already has a personality test record (in any course)
    $entry = $DB->get_record('personality_test', array('user' => $USER->id));
    
    if (!$entry) {
        $entry = new stdClass();
        $entry->user = $USER->id;
        $entry->course = $course;
        $entry->is_completed = 1;
        $entry->extraversion = $extra_res;
        $entry->introversion = $intra_res;
        $entry->sensing = $sensi_res;
        $entry->intuition = $intui_res;
        $entry->thinking = $ratio_res;
        $entry->feeling = $emoti_res;
        $entry->judging = $estru_res;
        $entry->perceptive = $perce_res;
        $entry->created_at = time();
        $entry->updated_at = time();
        
        // Add individual question responses
        foreach ($responses as $field => $value) {
            $entry->$field = $value;
        }
        
        $entry->id = $DB->insert_record('personality_test', $entry);
        return true;
    } else {
        // Update existing record
        $entry->is_completed = 1;
        $entry->course = $course;
        $entry->extraversion = $extra_res;
        $entry->introversion = $intra_res;
        $entry->sensing = $sensi_res;
        $entry->intuition = $intui_res;
        $entry->thinking = $ratio_res;
        $entry->feeling = $emoti_res;
        $entry->judging = $estru_res;
        $entry->perceptive = $perce_res;
        $entry->updated_at = time();
        
        // Add individual question responses
        foreach ($responses as $field => $value) {
            $entry->$field = $value;
        }
        
        $DB->update_record('personality_test', $entry);
        return true;
    }
}

/**
 * Helper function to get aggregated report data for a course.
 *
 * @param int $courseid The course ID.
 * @return array|false Array containing [$course, $students] or false if permission denied.
 */
function block_personality_test_get_report_data($courseid) {
    global $DB;
    
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id);
    
    require_login($course, false);
    
    // Check permissions
    if (!has_capability('block/personality_test:viewreports', $context)) {
        return false;
    }
    
    // Get enrolled students
    $enrolled_students = get_enrolled_users($context, 'block/personality_test:taketest', 0, 'u.id');
    $enrolled_ids = array_keys($enrolled_students);

    // Filter out admins/teachers
    $student_ids = array();
    foreach ($enrolled_ids as $candidateid) {
        $candidateid = (int)$candidateid;
        if (has_capability('block/personality_test:viewreports', $context, $candidateid)) {
            continue;
        }
        $student_ids[] = $candidateid;
    }
    
    $students = array();
    if (!empty($student_ids)) {
        list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED);
        $params['completed'] = 1;
        $students = $DB->get_records_select('personality_test', "user $insql AND is_completed = :completed", $params);
    }
    
    return [$course, $students];
}

/**
 * Helper function to calculate MBTI type string from test result.
 *
 * @param stdClass $entry The test result object.
 * @return string The MBTI type string (e.g., "INTJ").
 */
function block_personality_test_calculate_mbti($entry) {
    $mbti_score = "";
    $mbti_score .= ($entry->extraversion > $entry->introversion) ? "E" : "I";
    $mbti_score .= ($entry->sensing > $entry->intuition) ? "S" : "N";
    $mbti_score .= ($entry->thinking >= $entry->feeling) ? "T" : "F";
    $mbti_score .= ($entry->judging > $entry->perceptive) ? "J" : "P";
    return $mbti_score;
}
