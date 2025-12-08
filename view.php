<?php
require_once(dirname(__FILE__) . '/lib.php');

if( !isloggedin() ){
            return;
}

$courseid = optional_param('cid', 0, PARAM_INT);
$error  = optional_param('error', 0, PARAM_INT);

if ($courseid == SITEID && !$courseid) {
    redirect($CFG->wwwroot);
}

/*if (!isset($SESSION->honorcodetext)) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
}*/

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;
  
$PAGE->set_url('/blocks/personality_test/view.php', array('cid'=>$courseid));

$title = get_string('pluginname', 'block_personality_test');

$PAGE->set_pagelayout('print');
$PAGE->set_title($title." : ".$course->fullname);
$PAGE->set_heading($title." : ".$course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox');
echo "<h1 class='title_personality_test'>".get_string('test_page_title', 'block_personality_test')."</h1>";
echo "
<div>
".get_string('test_intro_p1', 'block_personality_test')." 
".get_string('test_intro_p2', 'block_personality_test')." 
</div>
<br>
<div style='background-color: #e3f2fd; border-left: 4px solid #2196F3; padding: 12px 16px; margin-bottom: 20px; border-radius: 4px;'>
    <strong>".get_string('test_benefit_note', 'block_personality_test')."</strong> ".get_string('test_benefit_required', 'block_personality_test')." (<span style='color: #d32f2f;'>*</span>)
</div>
";
$action_form = new moodle_url('/blocks/personality_test/save.php');
?>

<style>
    /* Estilo para campos obligatorios no completados solo después de intentar enviar */
    form.attempted select:invalid {
        border: 2px solid #d32f2f !important;
        background-color: #ffebee !important;
    }
    
    /* Mensaje visual al hacer focus en campo inválido */
    form.attempted select:invalid:focus {
        outline: 2px solid #d32f2f;
        box-shadow: 0 0 8px rgba(211, 47, 47, 0.3);
    }
</style>

<form method="POST" action="<?php echo $action_form ?>" id="personalityTestForm">
    <div class="content-accept <?php echo ($error)?"error":"" ?>">
        <?php if($error): ?>
            <p class="error"><?php echo get_string('required_message', 'block_personality_test') ?></p>
        <?php endif; ?>

        <ol class="personality_test_q">
        <?php for ($i=1;$i<=72;$i++){ ?>
        
        <li class="personality_test_item"><?php echo get_string("personality_test:q".$i, 'block_personality_test') ?>
        <select name="personality_test:q<?php echo $i; ?>" required>
            <option value="" disabled selected hidden><?php echo get_string('select_option', 'block_personality_test') ?></option>
            <option value="1"><?php echo get_string('personality_test:q'.$i.'_a', 'block_personality_test') ?></option>
            <option value="0"><?php echo get_string('personality_test:q'.$i.'_b', 'block_personality_test') ?></option>
        </select>
        </li>
        <?php } ?>
        </ol>
        <div class="clearfix"></div>
        <input class="btn" type="submit" id="submitBtn" value="<?php echo get_string('submit_text', 'block_personality_test') ?>" >
    
    </div>
    
    <input type="hidden" name="cid" value="<?php echo $courseid ?>">
    <div class="clearfix"></div>
    
</form>

<script>
// Marcar formulario cuando se haga clic en enviar
document.getElementById('submitBtn').addEventListener('click', function() {
    document.getElementById('personalityTestForm').classList.add('attempted');
});

// Mantener la clase attempted si hay error
<?php if($error): ?>
document.getElementById('personalityTestForm').classList.add('attempted');
<?php endif; ?>
</script>

<?php

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
