<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_personality_test_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025120901) {
        // Define index to be added to personality_test
        $table = new xmldb_table('personality_test');
        
        // Drop old non-unique index if exists
        $old_index = new xmldb_index('block_personality_test_user_idc', XMLDB_INDEX_NOTUNIQUE, ['user']);
        if ($dbman->index_exists($table, $old_index)) {
            $dbman->drop_index($table, $old_index);
        }

        // Clean up duplicate entries before creating unique index
        $sql = "SELECT user, COUNT(*) as count 
                FROM {personality_test} 
                GROUP BY user 
                HAVING count > 1";
        $duplicates = $DB->get_records_sql($sql);

        if ($duplicates) {
            foreach ($duplicates as $dup) {
                // Get all records for this user, ordered by id DESC
                // We want to keep the most recent one (highest ID)
                $records = $DB->get_records('personality_test', ['user' => $dup->user], 'id DESC');
                
                // Skip the first one (the one we want to keep)
                $first = true;
                foreach ($records as $record) {
                    if ($first) {
                        $first = false;
                        continue;
                    }
                    // Delete the rest
                    $DB->delete_records('personality_test', ['id' => $record->id]);
                }
            }
        }
        
        // Add unique index on user to prevent duplicate tests per user
        $index = new xmldb_index('user_unique', XMLDB_INDEX_UNIQUE, ['user']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Personality Test savepoint reached.
        upgrade_block_savepoint(true, 2025120901, 'personality_test');
    }

    if ($oldversion < 2025120903) {
        // Add fields for progressive save functionality
        $table = new xmldb_table('personality_test');

        // Add is_completed field
        $field = new xmldb_field('is_completed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'course');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add 72 question fields (q1 to q72)
        for ($i = 1; $i <= 72; $i++) {
            $prev_field = ($i == 1) ? 'is_completed' : 'q' . ($i - 1);
            
            $field = new xmldb_field('q' . $i, XMLDB_TYPE_INTEGER, '1', null, null, null, null, $prev_field);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Mark all existing records as completed (for backwards compatibility)
        // These are users who completed the test before the progressive save feature
        $DB->execute("UPDATE {personality_test} SET is_completed = 1 WHERE extraversion IS NOT NULL AND introversion IS NOT NULL");

        // Savepoint reached
        upgrade_block_savepoint(true, 2025120903, 'personality_test');
    }

    if ($oldversion < 2025120904) {
        // Change result columns to be nullable
        $table = new xmldb_table('personality_test');
        $result_fields = ['extraversion', 'introversion', 'sensing', 'intuition', 'thinking', 'feeling', 'judging', 'perceptive'];
        
        foreach ($result_fields as $fieldname) {
            $field = new xmldb_field($fieldname, XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_notnull($table, $field);
            }
        }

        // Savepoint reached
        upgrade_block_savepoint(true, 2025120904, 'personality_test');
    }

    // Remove course field as functionality is now cross-course
    if ($oldversion < 2025121700) {
        $table = new xmldb_table('personality_test');
        
        // Drop the course field if it exists
        $field = new xmldb_field('course');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        upgrade_block_savepoint(true, 2025121700, 'personality_test');
    }

    // Remove obsolete state field
    if ($oldversion < 2025121701) {
        $table = new xmldb_table('personality_test');
        
        // Drop the state field if it exists
        $field = new xmldb_field('state');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        upgrade_block_savepoint(true, 2025121701, 'personality_test');
    }

    return true;
}
