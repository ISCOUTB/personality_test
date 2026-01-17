<?php

class block_personality_test_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));

        // Checkbox/Select for showing descriptions. Default is No (0).
        $mform->addElement('selectyesno', 'config_showdescriptions', get_string('config_showdescriptions', 'block_personality_test'));
        $mform->setDefault('config_showdescriptions', 0); 
        $mform->setType('config_showdescriptions', PARAM_BOOL);
        $mform->addHelpButton('config_showdescriptions', 'config_showdescriptions', 'block_personality_test');
    }
}
