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
        
        // Add unique index on user to prevent duplicate tests per user
        $index = new xmldb_index('user_unique', XMLDB_INDEX_UNIQUE, ['user']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Personality Test savepoint reached.
        upgrade_block_savepoint(true, 2025120901, 'personality_test');
    }

    return true;
}
