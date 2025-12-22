<?php
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

// Display personality test icon centered above title
$iconurl = new moodle_url('/blocks/personality_test/pix/personality_test_icon.svg');
echo '<div style="text-align: center; margin-bottom: 15px;">';
echo '<img src="' . $iconurl . '" alt="Personality Test Icon" style="width: 70px; height: 70px; display: block; margin: 0 auto 10px auto;" />';
echo '</div>';

echo "<h1 class='title_personality_test' style='text-align: center;'>".get_string('test_page_title', 'block_personality_test')."</h1>";
echo "
<div>
".get_string('test_intro_p1', 'block_personality_test')." 
".get_string('test_intro_p2', 'block_personality_test')." 
</div>
<br>
<div style='background-color: #e0f7f1; border-left: 4px solid #00bf91; padding: 12px 16px; margin-bottom: 20px; border-radius: 4px;'>
    <strong>".get_string('test_benefit_note', 'block_personality_test')."</strong> ".get_string('test_benefit_required', 'block_personality_test')." (<span style='color: #d32f2f;'>*</span>)
</div>
";

$action_form = new moodle_url('/blocks/personality_test/save.php');
?>

<form method="POST" action="<?php echo $action_form ?>" id="personalityTestForm">
    <div class="content-accept">
        <ul class="personality_test_q">
        <?php 
        // Display current page questions
        for ($i=$start_question; $i<=$end_question; $i++){ 
            $field = "q{$i}";
            $saved_value = ($existing_response && isset($existing_response->$field)) ? $existing_response->$field : null;
        ?>
        
        <li class="personality_test_item" data-question="<?php echo $i; ?>">
            <div><?php echo get_string("personality_test:q".$i, 'block_personality_test') ?></div>
            <div class="answer-buttons">
                <label class="answer-btn option-a <?php echo ($saved_value === '1' || $saved_value === 1) ? 'selected' : ''; ?>" data-question="<?php echo $i; ?>" data-value="1">
                    <?php echo get_string('yes', 'block_personality_test'); ?>
                </label>
                <label class="answer-btn option-b <?php echo ($saved_value === '0' || $saved_value === 0) ? 'selected' : ''; ?>" data-question="<?php echo $i; ?>" data-value="0">
                    <?php echo get_string('no', 'block_personality_test'); ?>
                </label>
            </div>
            <select name="personality_test:q<?php echo $i; ?>" class="hidden-select select-q" id="select_q<?php echo $i; ?>" data-question="<?php echo $i; ?>">
                <option value="" disabled <?php echo ($saved_value === null) ? 'selected' : ''; ?> hidden><?php echo get_string('select_option', 'block_personality_test') ?></option>
                <option value="1" <?php echo ($saved_value === '1' || $saved_value === 1) ? 'selected' : ''; ?>><?php echo get_string('yes', 'block_personality_test'); ?></option>
                <option value="0" <?php echo ($saved_value === '0' || $saved_value === 0) ? 'selected' : ''; ?>><?php echo get_string('no', 'block_personality_test'); ?></option>
            </select>
        </li>
        <?php } ?>
        </ul>
        
        <!-- Hidden inputs for all previously answered questions from other pages -->
        <?php
        if ($existing_response) {
            for ($i = 1; $i <= 72; $i++) {
                // Skip questions on current page
                if ($i >= $start_question && $i <= $end_question) {
                    continue;
                }
                
                $field = "q{$i}";
                if (isset($existing_response->$field) && $existing_response->$field !== null) {
                    echo '<input type="hidden" name="personality_test:q'.$i.'" value="'.$existing_response->$field.'">';
                }
            }
        }
        ?>
        
        <div class="clearfix"></div>
        
        <!-- Navigation buttons -->
        <div class="navigation-buttons" style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
            <div>
                <?php if ($page > 1): ?>
                    <button type="submit" name="action" value="previous" class="btn btn-secondary">
                        <?php echo get_string('btn_previous', 'block_personality_test'); ?>
                    </button>
                <?php endif; ?>
            </div>
            
            <div>
                <?php if ($page < $total_pages): ?>
                    <button type="submit" name="action" value="next" class="btn btn-primary">
                        <?php echo get_string('btn_next', 'block_personality_test'); ?>
                    </button>
                <?php else: ?>
                    <button type="submit" name="action" value="finish" id="submitBtn" class="btn btn-success">
                        <?php echo get_string('btn_finish', 'block_personality_test'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    
    </div>
    
    <input type="hidden" name="cid" value="<?php echo $courseid ?>">
    <input type="hidden" name="page" value="<?php echo $page ?>">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    <div class="clearfix"></div>
    
</form>

<script>
// Auto-save functionality (silent, no visual feedback)
let autoSaveTimer = null;
let isSaving = false;

function autoSaveProgress() {
    if (isSaving) return;
    
    isSaving = true;
    const formData = new FormData(document.getElementById('personalityTestForm'));
    formData.set('action', 'autosave');
    
    fetch('<?php echo $CFG->wwwroot; ?>/blocks/personality_test/save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Silent save - no visual feedback
        isSaving = false;
    })
    .catch(error => {
        console.error('Auto-save error:', error);
        isSaving = false;
    });
}

// Manejar clics en los botones de respuesta
document.querySelectorAll('.answer-btn').forEach(function(button) {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        var question = this.getAttribute('data-question');
        var value = this.getAttribute('data-value');
        var select = document.getElementById('select_q' + question);
        
        // Actualizar el select oculto
        select.value = value;
        
        // Remover la clase selected de ambos botones de esta pregunta
        var allButtons = document.querySelectorAll('.answer-btn[data-question="' + question + '"]');
        allButtons.forEach(function(btn) {
            btn.classList.remove('selected');
        });
        
        // Agregar la clase selected al botón clickeado
        this.classList.add('selected');
        
        // Remove red highlight when user answers the question
        const listItem = this.closest('.personality_test_item');
        if (listItem && listItem.classList.contains('question-error-highlight')) {
            listItem.style.border = '';
            listItem.style.backgroundColor = '';
            listItem.style.borderRadius = '';
            listItem.style.padding = '';
            listItem.style.marginBottom = '';
            listItem.style.boxShadow = '';
            listItem.classList.remove('question-error-highlight');
        }
        
        // Clear previous timer
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
        }
        
        // Auto-save after 2 seconds of inactivity
        autoSaveTimer = setTimeout(autoSaveProgress, 2000);
    });
});

// Handle form submission for navigation
document.getElementById('personalityTestForm').addEventListener('submit', function(e) {
    const submitButton = e.submitter;
    const action = submitButton ? submitButton.value : 'next';
    
    // For "previous" button, always allow navigation without validation
    if (action === 'previous') {
        return true;
    }
    
    // Only validate for "next" and "finish" actions
    if (action !== 'next' && action !== 'finish') {
        return true;
    }
    
    // Validate current page for next/finish
    const selectsOnPage = document.querySelectorAll('.select-q');
    let allAnswered = true;
    let firstUnanswered = null;
    
    selectsOnPage.forEach(function(select) {
        if (select.value === '') {
            allAnswered = false;
            const listItem = select.closest('.personality_test_item');
            
            if (listItem) {
                listItem.style.border = '3px solid #d32f2f';
                listItem.style.backgroundColor = '#ffebee';
                listItem.style.borderRadius = '8px';
                listItem.style.padding = '24px 28px';
                listItem.style.marginBottom = '1.5rem';
                listItem.style.boxShadow = '0 4px 8px rgba(211, 47, 47, 0.3)';
                listItem.classList.add('question-error-highlight');
                
                if (!firstUnanswered) {
                    firstUnanswered = select;
                }
            }
        }
    });
    
    if (!allAnswered) {
        e.preventDefault();
        
        // Scroll to first unanswered question
        if (firstUnanswered) {
            firstUnanswered.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
        
        return false;
    }
});

// Auto-scroll to first unanswered question when continuing test
<?php if($existing_response && $answered_count > 0 && $answered_count < 72 && !$scroll_to_finish): ?>
window.addEventListener('load', function() {
    // Wait a bit for the page to fully render
    setTimeout(function() {
        // Find first unanswered question on current page
        const selects = document.querySelectorAll('.select-q');
        for (let i = 0; i < selects.length; i++) {
            if (selects[i].value === '') {
                const selectElement = selects[i];
                const listItem = selectElement.closest('.personality_test_item');
                
                // Add green highlight
                if (listItem) {
                    // Store original styles
                    const originalStyles = {
                        border: listItem.style.border,
                        backgroundColor: listItem.style.backgroundColor,
                        boxShadow: listItem.style.boxShadow
                    };
                    
                    listItem.style.border = '3px solid #28a745';
                    listItem.style.backgroundColor = '#d4edda';
                    listItem.style.boxShadow = '0 4px 8px rgba(40, 167, 69, 0.3)';
                    listItem.style.transition = 'all 0.3s ease';
                    
                    // Scroll to it
                    listItem.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    
                    // Remove highlight after 5 seconds
                    setTimeout(function() {
                        listItem.style.border = originalStyles.border;
                        listItem.style.backgroundColor = originalStyles.backgroundColor;
                        listItem.style.boxShadow = originalStyles.boxShadow;
                    }, 5000);
                }
                
                break;
            }
        }
    }, 300);
});
<?php endif; ?>

// Scroll to finish button when coming from block with all questions answered
<?php if($scroll_to_finish): ?>
window.addEventListener('load', function() {
    setTimeout(function() {
        const finishBtn = document.getElementById('submitBtn');
        if (finishBtn) {
            finishBtn.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            
            // Add green pulsing highlight to the button
            finishBtn.style.boxShadow = '0 0 20px rgba(40, 167, 69, 0.8)';
            finishBtn.style.transition = 'all 0.3s ease';
            
            // Remove highlight after 5 seconds
            setTimeout(function() {
                finishBtn.style.boxShadow = '';
            }, 5000);
        }
    }, 300);
});
<?php endif; ?>
</script>

<?php

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
