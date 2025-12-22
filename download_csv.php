<?php
/**
 * Descarga los resultados agregados del test de personalidad en formato CSV.
 *
 * @package   block_personality_test
 */

require_once(__DIR__ . '/../../config.php');

// Parámetros y Seguridad
$courseid = optional_param('courseid', 0, PARAM_INT);
$cid = optional_param('cid', 0, PARAM_INT);
$sesskey = optional_param('sesskey', '', PARAM_ALPHANUM);

// Determine course ID from either parameter
$courseid = $courseid ?: $cid;

if (!$courseid) {
    print_error('missingparam', '', '', 'courseid or cid');
}

// Globales de Moodle
global $DB, $USER, $COURSE, $PAGE, $OUTPUT, $CFG;

// Verificar clave de sesión si se proporciona
if ($sesskey) {
    require_sesskey($sesskey);
}

// Obtener curso y contexto
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

// Requerir inicio de sesión
require_login($course, false);

// Only teachers/managers (or site admins) should download aggregated exports.
if (!has_capability('block/personality_test:viewreports', $context) && !is_siteadmin()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

// Get enrolled students in this course
$enrolled_students = get_enrolled_users($context, 'block/personality_test:taketest', 0, 'u.id');
$enrolled_ids = array_keys($enrolled_students);

// Defensive: exclude report-capable users.
$student_ids = array();
foreach ($enrolled_ids as $candidateid) {
    $candidateid = (int)$candidateid;
    if (is_siteadmin($candidateid)) {
        continue;
    }
    if (has_capability('block/personality_test:viewreports', $context, $candidateid)) {
        continue;
    }
    $student_ids[] = $candidateid;
}
$enrolled_ids = $student_ids;

// Obtener datos de los estudiantes inscritos que han COMPLETADO el test
$students = array();
if (!empty($enrolled_ids)) {
    list($insql, $params) = $DB->get_in_or_equal($enrolled_ids, SQL_PARAMS_NAMED);
    $params['completed'] = 1;
    $students = $DB->get_records_select('personality_test', "user $insql AND is_completed = :completed", $params);
}

if (empty($students)) {
    // Redirigir si no hay datos, con un mensaje
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]), 
             get_string('sin_datos_estudiantes_pdf', 'block_personality_test'), 5);
    exit;
}

// --- PREPARACIÓN DE DATOS PARA EL INFORME ---

// Contador de tipos MBTI
$mbti_types = ["ISTJ", "ISFJ", "INFJ", "INTJ", "ISTP", "ISFP", "INFP", "INTP", 
               "ESTP", "ESFP", "ENFP", "ENTP", "ESTJ", "ESFJ", "ENFJ", "ENTJ"];
$mbti_count = array_fill_keys($mbti_types, 0);

// Contador de aspectos de personalidad
$aspect_counts = [
    "Introvertido" => 0, "Extrovertido" => 0,
    "Sensing" => 0, "Intuición" => 0,
    "Pensamiento" => 0, "Sentimiento" => 0,
    "Juicio" => 0, "Percepción" => 0
];

// Procesar datos para los resúmenes
foreach ($students as $entry) {
    if (!isset($entry->extraversion, $entry->introversion, $entry->sensing, 
              $entry->intuition, $entry->thinking, $entry->feeling, 
              $entry->judging, $entry->perceptive)) {
        continue;
    }

    $mbti_score = "";
    $mbti_score .= ($entry->extraversion > $entry->introversion) ? "E" : "I";
    $mbti_score .= ($entry->sensing > $entry->intuition) ? "S" : "N";
    $mbti_score .= ($entry->thinking >= $entry->feeling) ? "T" : "F";
    $mbti_score .= ($entry->judging > $entry->perceptive) ? "J" : "P";

    if (isset($mbti_count[$mbti_score])) {
        $mbti_count[$mbti_score]++;
    }

    $aspect_counts["Introvertido"] += ($entry->introversion >= $entry->extraversion) ? 1 : 0;
    $aspect_counts["Extrovertido"] += ($entry->extraversion > $entry->introversion) ? 1 : 0;
    $aspect_counts["Sensing"] += ($entry->sensing > $entry->intuition) ? 1 : 0;
    $aspect_counts["Intuición"] += ($entry->intuition >= $entry->sensing) ? 1 : 0;
    $aspect_counts["Pensamiento"] += ($entry->thinking >= $entry->feeling) ? 1 : 0;
    $aspect_counts["Sentimiento"] += ($entry->feeling > $entry->thinking) ? 1 : 0;
    $aspect_counts["Juicio"] += ($entry->judging > $entry->perceptive) ? 1 : 0;
    $aspect_counts["Percepción"] += ($entry->perceptive >= $entry->judging) ? 1 : 0;
}

// --- GENERACIÓN DEL CSV ---

// Generar nombre elegante del archivo usando string de idioma
$course_name = preg_replace('/[^a-z0-9]/i', '_', strtolower($course->shortname));
$date_str = date('Y-m-d');
$filename = get_string('export_filename', 'block_personality_test') . '_' . $course_name . '_' . $date_str . '.csv';
$filename = clean_filename($filename);

// Cabeceras HTTP para forzar la descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Abrir flujo de salida PHP
$output = fopen('php://output', 'w');

// UTF-8 BOM para Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// --- SECCIÓN DE METADATOS ---
fputcsv($output, [get_string('pdf_report_title', 'block_personality_test')]);
fputcsv($output, [get_string('pdf_report_subject', 'block_personality_test', $course->fullname)]);
fputcsv($output, [get_string('generated_on', 'block_personality_test') . ': ' . userdate(time(), get_string('strftimedatefullshort', 'langconfig'))]);
fputcsv($output, [get_string('total_students_processed', 'block_personality_test', count($students))]);
fputcsv($output, [' ']); // Línea en blanco para separar secciones

// --- SECCIÓN DE DISTRIBUCIÓN MBTI ---
fputcsv($output, [get_string('titulo_distribucion_mbti', 'block_personality_test')]);
fputcsv($output, [get_string('mbti_type', 'block_personality_test'), get_string('count', 'block_personality_test'), get_string('percentage', 'block_personality_test')]);

// Calcular total para porcentajes
$total_mbti = array_sum(array_values($mbti_count));

// Mostrar solo los tipos MBTI presentes
foreach ($mbti_count as $type => $count) {
    if ($count > 0) {
        $percentage = ($total_mbti > 0) ? round(($count / $total_mbti) * 100, 1) : 0;
        fputcsv($output, [$type, $count, $percentage . '%']);
    }
}
fputcsv($output, [' ']); // Línea en blanco para separar secciones

// --- SECCIÓN DE DISTRIBUCIÓN DE RASGOS ---
fputcsv($output, [get_string('titulo_distribucion_rasgos', 'block_personality_test')]);

// Introversión / Extroversión
fputcsv($output, [get_string('introversion_extroversion', 'block_personality_test')]);
fputcsv($output, [get_string('trait', 'block_personality_test'), get_string('count', 'block_personality_test')]);
fputcsv($output, [get_string('Introvertido', 'block_personality_test'), $aspect_counts['Introvertido']]);
fputcsv($output, [get_string('Extrovertido', 'block_personality_test'), $aspect_counts['Extrovertido']]);
fputcsv($output, [' ']);

// Sensación / Intuición
fputcsv($output, [get_string('sensacion_intuicion', 'block_personality_test')]);
fputcsv($output, [get_string('trait', 'block_personality_test'), get_string('count', 'block_personality_test')]);
fputcsv($output, [get_string('Sensing', 'block_personality_test'), $aspect_counts['Sensing']]);
fputcsv($output, [get_string('Intuicion', 'block_personality_test'), $aspect_counts['Intuición']]);
fputcsv($output, [' ']);

// Pensamiento / Sentimiento
fputcsv($output, [get_string('pensamiento_sentimiento', 'block_personality_test')]);
fputcsv($output, [get_string('trait', 'block_personality_test'), get_string('count', 'block_personality_test')]);
fputcsv($output, [get_string('Pensamiento', 'block_personality_test'), $aspect_counts['Pensamiento']]);
fputcsv($output, [get_string('Sentimiento', 'block_personality_test'), $aspect_counts['Sentimiento']]);
fputcsv($output, [' ']);

// Juicio / Percepción
fputcsv($output, [get_string('juicio_percepcion', 'block_personality_test')]);
fputcsv($output, [get_string('trait', 'block_personality_test'), get_string('count', 'block_personality_test')]);
fputcsv($output, [get_string('Juicio', 'block_personality_test'), $aspect_counts['Juicio']]);
fputcsv($output, [get_string('Percepcion', 'block_personality_test'), $aspect_counts['Percepción']]);
fputcsv($output, [' ']);
fputcsv($output, [' ']);

// --- SECCIÓN DE DATOS DETALLADOS ---
fputcsv($output, [get_string('summary_information', 'block_personality_test') . ' - ' . get_string('csv_header_fullname', 'block_personality_test')]);
fputcsv($output, [
    get_string('csv_header_userid', 'block_personality_test'),
    get_string('csv_header_fullname', 'block_personality_test'),
    get_string('csv_header_mbti_type', 'block_personality_test'),
    'E', 'I', 'S', 'N', 'T', 'F', 'J', 'P',
    get_string('csv_header_last_action_date', 'block_personality_test')
]);

// Procesar y escribir datos de cada estudiante
foreach ($students as $entry) {
    // Omitir entradas incompletas
    if (!isset($entry->extraversion, $entry->introversion, $entry->sensing, 
              $entry->intuition, $entry->thinking, $entry->feeling, 
              $entry->judging, $entry->perceptive)) {
        continue;
    }
    
    // Obtener información del usuarios
    $student_user = $DB->get_record('user', ['id' => $entry->user], 'id, firstname, lastname, idnumber');
    $fullname = fullname($student_user);

    // Calcular tipo MBTI
    $mbti_score = "";
    $mbti_score .= ($entry->extraversion >= $entry->introversion) ? "E" : "I";
    $mbti_score .= ($entry->sensing > $entry->intuition) ? "S" : "N";
    $mbti_score .= ($entry->thinking >= $entry->feeling) ? "T" : "F";
    $mbti_score .= ($entry->judging > $entry->perceptive) ? "J" : "P";

    // Escribir fila en el CSV
    // Use last_action (guaranteed present)
    $lastaction = $entry->last_action;

    fputcsv($output, [
        $student_user->idnumber,
        $fullname,
        $mbti_score,
        $entry->extraversion,
        $entry->introversion,
        $entry->sensing,
        $entry->intuition,
        $entry->thinking,
        $entry->feeling,
        $entry->judging,
        $entry->perceptive,
        date('Y-m-d H:i:s', $lastaction)
    ]);
}

fclose($output);
exit; // Terminar script después de generar el CSV

?>
