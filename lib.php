<?php 

require_once(dirname(__FILE__) . '/../../config.php');

function save_personality_test($course,$extra_res,$intra_res,$sensi_res,$intui_res,$ratio_res,$emoti_res,$estru_res,$perce_res, $responses = array()) {
    GLOBAL $DB, $USER, $CFG;
    // Check if user already has a personality test record (in any course)
    $entry = $DB->get_record('personality_test', array('user' => $USER->id));
    
    if (!$entry) {
        $entry = new stdClass();
        $entry->user = $USER->id;
        $entry->course = $course;
        $entry->state = "1";
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

