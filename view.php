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
                 array('cid' => $courseid, 'page' => $max_allowed_page)),
                 get_string('complete_previous_pages', 'block_personality_test'),
                 null, \core\output\notification::NOTIFY_WARNING);
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
    /* Estilos específicos solo para el formulario del test - con mayor especificidad */
    body#page-blocks-personality_test-view .title_personality_test {
        font-size: 2rem;
        font-weight: 600;
        color: #005B9A;
        margin-bottom: 1.5rem;
        text-align: center;
        letter-spacing: -0.5px;
    }
    
    body#page-blocks-personality_test-view #personalityTestForm {
        max-width: 900px;
        margin: 0 auto;
    }
    
    body#page-blocks-personality_test-view .personality_test_q {
        list-style: none;
        padding: 0;
        margin: 2rem 0;
    }
    
    body#page-blocks-personality_test-view .personality_test_item {
        background: #ffffff;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 24px 28px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 6px rgba(0, 91, 154, 0.06);
        transition: all 0.3s ease;
        font-size: 1.05rem;
        line-height: 1.6;
        color: #37474f;
    }
    
    body#page-blocks-personality_test-view .personality_test_item:hover {
        box-shadow: 0 4px 12px rgba(0, 91, 154, 0.12);
        transform: translateY(-2px);
        border-color: #005B9A;
    }
    
    /* Ocultar el select original */
    body#page-blocks-personality_test-view .personality_test_item select {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        pointer-events: none;
    }
    
    /* Contenedor de botones */
    body#page-blocks-personality_test-view .personality_test_item .answer-buttons {
        display: flex;
        gap: 12px;
        margin-top: 18px;
        width: 100%;
    }
    
    /* Botones de respuesta */
    body#page-blocks-personality_test-view .personality_test_item .answer-btn {
        flex: 1;
        padding: 14px 20px;
        border: 2px solid #e0e0e0;
        background: #ffffff;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 500;
        color: #546e7a;
        cursor: pointer;
        transition: all 0.3s ease;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    body#page-blocks-personality_test-view .personality_test_item .answer-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 91, 154, 0.15);
    }
    
    body#page-blocks-personality_test-view .personality_test_item .answer-btn.option-a {
        border-color: #005B9A;
        color: #005B9A;
    }
    
    body#page-blocks-personality_test-view .personality_test_item .answer-btn.option-a:hover {
        background: #f0f7fc;
    }
    
    body#page-blocks-personality_test-view .personality_test_item .answer-btn.option-a.selected {
        background: linear-gradient(135deg, #005B9A 0%, #004a7c 100%);
        border-color: #005B9A;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(0, 91, 154, 0.3);
    }
    
    body#page-blocks-personality_test-view .personality_test_item .answer-btn.option-b {
        border-color: #00B5E2;
        color: #00B5E2;
    }
    
    body#page-blocks-personality_test-view .personality_test_item .answer-btn.option-b:hover {
        background: #e6f7fd;
    }
    
    body#page-blocks-personality_test-view .personality_test_item .answer-btn.option-b.selected {
        background: linear-gradient(135deg, #00B5E2 0%, #0095c7 100%);
        border-color: #00B5E2;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(0, 181, 226, 0.3);
    }
    
    /* Estilo para preguntas sin responder después de intentar enviar */
    body#page-blocks-personality_test-view form.attempted .personality_test_item:has(select:invalid) {
        border: 2px solid #d32f2f !important;
        background-color: #fff5f5 !important;
    }
    
    body#page-blocks-personality_test-view form.attempted .personality_test_item:has(select:invalid):hover {
        box-shadow: 0 4px 12px rgba(211, 47, 47, 0.15);
    }
    
    /* Botón de envío */
    body#page-blocks-personality_test-view #personalityTestForm input[type="submit"].btn {
        background: linear-gradient(135deg, #005B9A 0%, #004a7c 100%);
        color: white;
        border: none;
        padding: 16px 48px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 91, 154, 0.3);
        display: block;
        margin: 2.5rem auto;
        min-width: 200px;
    }
    
    body#page-blocks-personality_test-view #personalityTestForm input[type="submit"].btn:hover {
        background: linear-gradient(135deg, #00B5E2 0%, #0095c7 100%);
        box-shadow: 0 6px 20px rgba(0, 181, 226, 0.4);
        transform: translateY(-2px);
    }
    
    body#page-blocks-personality_test-view #personalityTestForm input[type="submit"].btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 8px rgba(0, 91, 154, 0.3);
    }
    
    /* Mensaje de error */
    body#page-blocks-personality_test-view .content-accept .error {
        background-color: #ffebee;
        color: #c62828;
        padding: 16px 20px;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #d32f2f;
        font-weight: 500;
    }
    
    /* Mejoras generales de tipografía solo para el test */
    body#page-blocks-personality_test-view .generalbox {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    
    /* Estilos para el cuadro informativo */
    body#page-blocks-personality_test-view .generalbox > div:first-of-type {
        font-size: 1.05rem;
        line-height: 1.7;
        color: #455a64;
        margin-bottom: 1rem;
        padding: 0 4px;
    }
    
    body#page-blocks-personality_test-view .generalbox > div[style*="background-color"] {
        background: linear-gradient(135deg, #e8f4f8 0%, #d4ebf7 100%) !important;
        border-left: 4px solid #005B9A !important;
        padding: 16px 20px !important;
        margin-bottom: 2rem !important;
        border-radius: 8px !important;
        box-shadow: 0 2px 6px rgba(0, 91, 154, 0.08);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        body#page-blocks-personality_test-view .personality_test_item .answer-buttons {
            flex-direction: column;
        }
        
        body#page-blocks-personality_test-view .personality_test_item {
            padding: 20px 22px;
        }
        
        body#page-blocks-personality_test-view .navigation-buttons {
            flex-direction: column !important;
            gap: 10px;
        }
        
        body#page-blocks-personality_test-view .navigation-buttons > div {
            width: 100%;
        }
        
        body#page-blocks-personality_test-view .navigation-buttons button {
            width: 100%;
        }
    }
    
    /* Navigation buttons - only within the form */
    body#page-blocks-personality_test-view #personalityTestForm .navigation-buttons .btn-secondary {
        background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
        color: white !important;
        border: none !important;
        padding: 14px 32px !important;
        font-size: 1rem !important;
        font-weight: 600 !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 4px 10px rgba(108, 117, 125, 0.3) !important;
    }
    
    body#page-blocks-personality_test-view #personalityTestForm .navigation-buttons .btn-secondary:hover {
        background: linear-gradient(135deg, #5a6268 0%, #545b62 100%) !important;
        box-shadow: 0 6px 16px rgba(108, 117, 125, 0.4) !important;
        transform: translateY(-2px) !important;
    }
    
    body#page-blocks-personality_test-view #personalityTestForm .navigation-buttons .btn-primary {
        background: linear-gradient(135deg, #005B9A 0%, #004a7c 100%) !important;
        color: white !important;
        border: none !important;
        padding: 14px 32px !important;
        font-size: 1rem !important;
        font-weight: 600 !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 4px 10px rgba(0, 91, 154, 0.3) !important;
    }
    
    body#page-blocks-personality_test-view #personalityTestForm .navigation-buttons .btn-primary:hover {
        background: linear-gradient(135deg, #00B5E2 0%, #0095c7 100%) !important;
        box-shadow: 0 6px 16px rgba(0, 181, 226, 0.4) !important;
        transform: translateY(-2px) !important;
    }
    
    body#page-blocks-personality_test-view #personalityTestForm .navigation-buttons .btn-success {
        background: linear-gradient(135deg, #28a745 0%, #218838 100%) !important;
        color: white !important;
        border: none !important;
        padding: 14px 32px !important;
        font-size: 1rem !important;
        font-weight: 600 !important;
        border-radius: 8px !important;
        cursor: pointer !important;
        transition: all 0.3s ease !important;
        box-shadow: 0 4px 10px rgba(40, 167, 69, 0.3) !important;
    }
    
    body#page-blocks-personality_test-view #personalityTestForm .navigation-buttons .btn-success:hover {
        background: linear-gradient(135deg, #218838 0%, #1e7e34 100%) !important;
        box-shadow: 0 6px 16px rgba(40, 167, 69, 0.4) !important;
        transform: translateY(-2px) !important;
    }
</style>

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
                <option value="1" <?php echo ($saved_value === '1' || $saved_value === 1) ? 'selected' : ''; ?>>Yes</option>
                <option value="0" <?php echo ($saved_value === '0' || $saved_value === 0) ? 'selected' : ''; ?>>No</option>
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
