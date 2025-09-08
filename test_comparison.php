<?php
/**
 * Archivo de prueba para verificar cómo funciona el botón en personality_test
 * Uso: /blocks/personality_test/test_comparison.php?courseid=1
 */

require_once('../../config.php');

$courseid = required_param('courseid', PARAM_INT);

// Obtener curso y contexto
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course, false);

// Configurar página
$PAGE->set_url('/blocks/personality_test/test_comparison.php', array('courseid' => $courseid));
$PAGE->set_title('Comparación con personality_test');
$PAGE->set_heading('Comparación con personality_test');
$PAGE->set_context($context);

echo $OUTPUT->header();

echo '<div style="max-width: 800px; margin: 20px auto; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">';
echo '<h2>Comparación: personality_test vs learning_style</h2>';

// Verificar capacidades
$cap_standard = has_capability('moodle/course:viewhiddensections', $context);

echo '<h3>Estado de permisos:</h3>';
echo '<ul>';
echo '<li><strong>moodle/course:viewhiddensections:</strong> ' . ($cap_standard ? 'SÍ' : 'NO') . '</li>';
echo '</ul>';

echo '<h3>Prueba de botones:</h3>';

if ($cap_standard) {
    echo '<div style="background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; margin: 10px 0;">';
    echo '<strong>✅ PERSONALITY TEST</strong><br>';
    $csv_url = new moodle_url('/blocks/personality_test/download_csv.php', ['courseid' => $courseid, 'sesskey' => sesskey()]);
    $pdf_url = new moodle_url('/blocks/personality_test/download_pdf.php', ['courseid' => $courseid, 'sesskey' => sesskey()]);
    echo '<a href="' . $csv_url . '" class="btn btn-success" style="margin: 5px; padding: 8px 16px; background: #28a745; color: white; text-decoration: none;">CSV</a>';
    echo '<a href="' . $pdf_url . '" class="btn btn-success" style="margin: 5px; padding: 8px 16px; background: #28a745; color: white; text-decoration: none;">PDF</a>';
    echo '</div>';
    
    echo '<div style="background: #d1ecf1; padding: 15px; border: 1px solid #bee5eb; margin: 10px 0;">';
    echo '<strong>✅ LEARNING STYLE</strong><br>';
    $learning_url = new moodle_url('/blocks/learning_style/download_results.php', ['courseid' => $courseid, 'sesskey' => sesskey()]);
    echo '<a href="' . $learning_url . '" class="btn btn-primary" style="margin: 5px; padding: 8px 16px; background: #007bff; color: white; text-decoration: none;">Descargar Resultados CSV</a>';
    echo '</div>';
} else {
    echo '<div style="background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; color: #721c24;">';
    echo '<strong>❌ Sin permisos:</strong> No puedes ver ningún botón de descarga.';
    echo '</div>';
}

// Datos disponibles
echo '<h3>Datos disponibles:</h3>';
$personality_count = $DB->count_records('personality_test', array('course' => $courseid));
$learning_count = $DB->count_records('learning_style', array('course' => $courseid));

echo '<ul>';
echo '<li><strong>Personality Test:</strong> ' . $personality_count . ' registros</li>';
echo '<li><strong>Learning Style:</strong> ' . $learning_count . ' registros</li>';
echo '</ul>';

echo '</div>';

echo $OUTPUT->footer();
?>
