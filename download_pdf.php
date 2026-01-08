<?php
/**
 * Personality Test PDF Download
 *
 * @package    block_personality_test
 * @copyright  2026 SAVIO - Sistema de Aprendizaje Virtual Interactivo (UTB)
 * @author     SAVIO Development Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// --- INCLUDES Y CONFIGURACIÓN INICIAL DE MOODLE ---
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');

// --- PARÁMETROS Y SEGURIDAD ---
$courseid = optional_param('courseid', 0, PARAM_INT);
$cid = optional_param('cid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$sesskey = optional_param('sesskey', '', PARAM_ALPHANUM);

// Determine course ID from either parameter
$courseid = $courseid ?: $cid;

if (!$courseid) {
    print_error('missingparam', '', '', 'courseid or cid');
}

// Moodle globals
global $DB, $USER, $COURSE, $PAGE, $OUTPUT, $CFG;

// Verifica la clave de sesión si se proporciona
if ($sesskey) {
    require_sesskey($sesskey);
}

// Define el nombre del plugin para usar en get_string
define('BLOCK_PT_LANG', 'block_personality_test');

// --- OBTENCIÓN Y PROCESAMIENTO DE DATOS ---
if ($userid) {
    // Individual report logic
    $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    $context = context_course::instance($course->id);
    require_login($course, false);

    $canviewreports = has_capability('block/personality_test:viewreports', $context) || is_siteadmin();
    if (!$canviewreports && (int)$userid !== (int)$USER->id) {
        redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    }
    
    $students = $DB->get_records('personality_test', ['user' => $userid, 'is_completed' => 1]);
} else {
    // Aggregated report logic using helper
    $report_data = block_personality_test_get_report_data($courseid);
    if ($report_data === false) {
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    }
    list($course, $students) = $report_data;
    $context = context_course::instance($course->id);
}

if (empty($students)) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]), get_string('sin_datos_estudiantes_pdf', BLOCK_PT_LANG), 5);
    exit;
}

// Inicialización de contadores
$mbti_types = ["ISTJ", "ISFJ", "INFJ", "INTJ", "ISTP", "ISFP", "INFP", "INTP", "ESTP", "ESFP", "ENFP", "ENTP", "ESTJ", "ESFJ", "ENFJ", "ENTJ"];
$mbti_count = array_fill_keys($mbti_types, 0);
$total_students_processed = 0;

$aspect_keys = [
    'Introvertido', 'Extrovertido', 'Sensing', 'Intuicion',
    'Pensamiento', 'Sentimiento', 'Juicio', 'Percepcion'
];
$aspect_counts = array_fill_keys($aspect_keys, 0);


// Procesamiento de datos de estudiantes
foreach ($students as $entry) {
    if (!isset($entry->extraversion, $entry->introversion, $entry->sensing, $entry->intuition, $entry->thinking, $entry->feeling, $entry->judging, $entry->perceptive)) {
        // Omitir registros de estudiantes sin datos.
        continue;
    }

    $mbti_score = block_personality_test_calculate_mbti($entry);

    if (isset($mbti_count[$mbti_score])) {
        $mbti_count[$mbti_score]++;
    }

    $aspect_counts['Introvertido'] += ($entry->introversion >= $entry->extraversion) ? 1 : 0;
    $aspect_counts['Extrovertido'] += ($entry->extraversion > $entry->introversion) ? 1 : 0;
    $aspect_counts['Sensing'] += ($entry->sensing > $entry->intuition) ? 1 : 0;
    $aspect_counts['Intuicion'] += ($entry->intuition >= $entry->sensing) ? 1 : 0;
    $aspect_counts['Pensamiento'] += ($entry->thinking >= $entry->feeling) ? 1 : 0;
    $aspect_counts['Sentimiento'] += ($entry->feeling > $entry->thinking) ? 1 : 0;
    $aspect_counts['Juicio'] += ($entry->judging > $entry->perceptive) ? 1 : 0;
    $aspect_counts['Percepcion'] += ($entry->perceptive >= $entry->judging) ? 1 : 0;

    $total_students_processed++;
}

$mbti_count_filtered = $mbti_count;

// Check if this is an individual report
$is_individual_report = ($userid && count($students) == 1);
$student_data = null;
$student_user = null;
$student_mbti = '';

if ($is_individual_report) {
    $student_data = reset($students);
    $student_user = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname, email, idnumber');
    // Calculate MBTI for individual
    $student_mbti = block_personality_test_calculate_mbti($student_data);
}

// --- CLASE PDF PERSONALIZADA (ENCABEZADO/PIE CON LOGO) ---
class PersonalityReportPDF extends TCPDF {
    private $coursefullname = '';
    private $reporttitle = '';

    public function setCourseFullName($name) { $this->coursefullname = $name; }
    public function setReportTitle($title) { $this->reporttitle = $title; }

    // ******** HEADER MODIFICADO PARA INCLUIR LOGO ********
    public function Header() {
        global $CFG; // Necesitamos $CFG para obtener la ruta raíz de Moodle

        // --- Inicio: Añadir Logo ---
        $logoname = 'logo_utb.png'; // <-- IMPORTANTE: Cambia esto al nombre exacto de tu archivo de logo
        $logopath = $CFG->dirroot . '/blocks/personality_test/pix/' . $logoname;

        // Define el tamaño y posición del logo (en mm)
        $logo_width = 25; // Ancho deseado del logo (ajusta según necesites)
        $logo_x = PDF_MARGIN_LEFT; // Posición X = Margen izquierdo
        $logo_y = 5; // Posición Y (ej. 5mm desde el borde superior de la página, ajusta si es necesario)

        // Dibuja el logo si el archivo existe
        if (file_exists($logopath)) {
            // Parámetros: ruta, x, y, ancho (0 = auto altura), tipo (vacío=auto), link, align, resize, dpi, palign...
            $this->Image($logopath, $logo_x, $logo_y, $logo_width, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
        // --- Fin: Añadir Logo ---

        // Mover el cursor Y un poco hacia abajo para empezar el texto DESPUÉS del logo
        // Ajusta el valor '20' según el tamaño real de tu logo + el espacio deseado
        $text_start_y = $logo_y + 15; // Puedes experimentar con este valor
        $this->SetY($text_start_y);

        // --- Texto Original del Encabezado ---
        // Centrar el texto del título
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, $this->reporttitle ?: get_string('pdf_report_title', 'block_personality_test'), 0, true, 'C', 0, '', 0, false, 'T', 'M');

        // Centrar el nombre del curso
        $this->SetFont('helvetica', '', 9);
        $this->Cell(0, 10, get_string('course', 'moodle') .': '. $this->coursefullname, 0, true, 'C', 0, '', 0, false, 'T', 'M');

        // Dibujar la línea horizontal debajo del texto
        $line_y = $this->GetY(); // Obtener la posición Y después del texto
        $this->Line(PDF_MARGIN_LEFT, $line_y, $this->getPageWidth() - PDF_MARGIN_RIGHT, $line_y);
    }

    // El método Footer() 
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());
        $texto_pie = get_string('page', 'moodle') .' '. $this->getAliasNumPage().'/'.$this->getAliasNbPages();
        $texto_pie .= ' | ' . get_string('generated_on', 'block_personality_test') . ': ' . userdate(time(), get_string('strftimedatetimeshort', 'langconfig'));
        $this->Cell(0, 10, $texto_pie, 0, false, 'C');
    }
}



// --- INICIALIZACIÓN Y CONFIGURACIÓN DEL OBJETO PDF ---
$pdf = new PersonalityReportPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Establece metadatos
$pdf->SetCreator(fullname($USER));
$pdf->SetAuthor(get_string('pluginname', 'block_personality_test'));
$report_title_str = get_string('pdf_report_title', 'block_personality_test');
$pdf->SetTitle($report_title_str);
$pdf->SetSubject(get_string('pdf_report_subject', 'block_personality_test', format_string($course->fullname)));

// Pasa datos al encabezado/pie personalizados
$pdf->setCourseFullName(format_string($course->fullname));
$pdf->setReportTitle($report_title_str);

// Configuración estándar de TCPDF
// ******** AJUSTA LOS MÁRGENES AQUÍ ********
// Aumenta el segundo parámetro (Margen Superior) y el HeaderMargin para dar espacio al logo
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 30, PDF_MARGIN_RIGHT); // Ejemplo: Margen superior aumentado
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER + 15); // Ejemplo: Margen del Header aumentado (ajusta según necesites)
// ******** FIN DE AJUSTE DE MÁRGENES ********

$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Añade la primera página
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// --- GENERACIÓN DEL CONTENIDO DEL PDF ---

if ($is_individual_report) {
    // ============ REPORTE INDIVIDUAL ============
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, get_string('individual_results', 'block_personality_test'), 0, 1, 'C');
    $pdf->Ln(5);
    
    // Información del estudiante
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, get_string('student_name', 'block_personality_test') . ':', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, fullname($student_user), 0, 1, 'L');
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, get_string('email', 'block_personality_test') . ':', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, $student_user->email, 0, 1, 'L');
    $pdf->Ln(2);
    
    if (!empty($student_user->idnumber)) {
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, 'ID:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $student_user->idnumber, 0, 1, 'L');
        $pdf->Ln(2);
    }
    
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, get_string('test_date', 'block_personality_test') . ':', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, userdate($student_data->created_at, get_string('strftimedatetimeshort', 'langconfig')), 0, 1, 'L');
    $pdf->Ln(5);
    
    // Tipo MBTI
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 10, get_string('mbti_type', 'block_personality_test') . ': ' . $student_mbti, 0, 1, 'C');
    $pdf->SetFont('helvetica', 'I', 9);
    $mbti_dimensions_key = 'mbti_dimensions_' . strtolower($student_mbti);
    $pdf->Cell(0, 6, '(' . get_string($mbti_dimensions_key, 'block_personality_test') . ')', 0, 1, 'C');
    $pdf->Ln(8);
    
    // Descripción del tipo MBTI
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, get_string('personality_dimensions', 'block_personality_test'), 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $mbti_key = 'mbti_' . strtolower($student_mbti);
    $pdf->MultiCell(0, 5, get_string($mbti_key, 'block_personality_test'), 0, 'J');
    $pdf->Ln(8);
    
    // Tabla de puntuaciones detalladas
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 8, get_string('detailed_scores', 'block_personality_test'), 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    $html_scores = '<table border="1" cellpadding="4" cellspacing="0">';
    $html_scores .= '<thead><tr style="background-color:#eeeeee; font-weight:bold;">';
    $html_scores .= '<th width="30%" align="center">' . get_string('dimension', 'block_personality_test') . '</th>';
    $html_scores .= '<th width="40%" align="center">' . get_string('score', 'block_personality_test') . '</th>';
    $html_scores .= '<th width="30%" align="center">' . get_string('preference', 'block_personality_test') . '</th>';
    $html_scores .= '</tr></thead><tbody>';
    
    $dimensions = [
        ['E/I', $student_data->extraversion, $student_data->introversion, 
         get_string('extraversion', 'block_personality_test'), get_string('introversion', 'block_personality_test')],
        ['S/N', $student_data->sensing, $student_data->intuition,
         get_string('sensing', 'block_personality_test'), get_string('intuition', 'block_personality_test')],
        ['T/F', $student_data->thinking, $student_data->feeling,
         get_string('thinking', 'block_personality_test'), get_string('feeling', 'block_personality_test')],
        ['J/P', $student_data->judging, $student_data->perceptive,
         get_string('judging', 'block_personality_test'), get_string('perceptive', 'block_personality_test')]
    ];
    
    foreach ($dimensions as $dim) {
        $dominant = $dim[1] >= $dim[2] ? $dim[3] : $dim[4];
        $html_scores .= '<tr>';
        $html_scores .= '<td width="30%" align="center"><strong>' . $dim[0] . '</strong></td>';
        $html_scores .= '<td width="40%" align="center">' . $dim[1] . ' / ' . $dim[2] . '</td>';
        $html_scores .= '<td width="30%" align="center">' . $dominant . '</td>';
        $html_scores .= '</tr>';
    }
    
    $html_scores .= '</tbody></table>';
    $pdf->writeHTML($html_scores, true, false, true, false, '');
    
} else {
    // ============ REPORTE AGREGADO (ORIGINAL) ============
    // Sección Resumen
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(0, 10, get_string('summary_information', 'block_personality_test'), 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, get_string('total_students_processed', 'block_personality_test', $total_students_processed), 0, 1, 'L');
    $pdf->Ln(5);

// Sección Tabla MBTI
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, get_string('titulo_distribucion_mbti', 'block_personality_test'), 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);

$html_mbti = '<table border="1" cellpadding="4" cellspacing="0">';
$html_mbti .= '<thead><tr style="background-color:#eeeeee; font-weight:bold;">';
$html_mbti .= '<th width="25%" align="center">' . get_string('mbti_type', 'block_personality_test') . '</th>';
// Usar 'num_estudiantes_header' consistentemente
$html_mbti .= '<th width="35%" align="center">' . get_string('num_estudiantes_header', 'block_personality_test') . '</th>';
// Usar 'percentage' consistentemente
$html_mbti .= '<th width="40%" align="center">' . get_string('percentage', 'block_personality_test') . '</th>';
$html_mbti .= '</tr></thead><tbody>';

arsort($mbti_count_filtered);
foreach ($mbti_count_filtered as $type => $count) {
    $percentage = ($total_students_processed > 0) ? round(($count / $total_students_processed) * 100, 1) : 0;
    $html_mbti .= '<tr>';
    $html_mbti .= '<td width="25%" align="center">' . $type . '</td>';
    $html_mbti .= '<td width="35%" align="center">' . $count . '</td>';
    $html_mbti .= '<td width="40%" align="right">' . number_format($percentage, 1, ',', '.') . '%</td>';
    $html_mbti .= '</tr>';
}
$html_mbti .= '<tr style="background-color:#f8f8f8; font-weight:bold;">';
$html_mbti .= '<td width="25%" align="center">' . get_string('total', 'moodle') . '</td>';
$html_mbti .= '<td width="35%" align="center">' . $total_students_processed . '</td>';
$html_mbti .= '<td width="40%" align="right">' . ($total_students_processed > 0 ? '100,0%' : '0,0%') . '</td>';
$html_mbti .= '</tr></tbody></table>';
$pdf->writeHTML($html_mbti, true, false, true, false, '');
$pdf->Ln(8);

// Sección Tablas de Rasgos
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 10, get_string('titulo_distribucion_rasgos', 'block_personality_test'), 0, 1, 'L');
$pdf->SetFont('helvetica', '', 9);

// Función auxiliar para HTML de tabla de aspecto
function crearTablaAspectoHTML($titulo_key, $label1_key, $count1, $label2_key, $count2, $total) {
    $perc1 = ($total > 0) ? round(($count1 / $total) * 100, 1) : 0;
    $perc2 = ($total > 0) ? round(($count2 / $total) * 100, 1) : 0;

    $titulo = get_string($titulo_key, 'block_personality_test');
    $label1 = get_string($label1_key, 'block_personality_test');
    $label2 = get_string($label2_key, 'block_personality_test');

    $table = '<table border="1" cellpadding="3" cellspacing="0">';
    $table .= '<thead><tr style="background-color:#eeeeee; font-weight:bold;"><th colspan="3" align="center">' . $titulo . '</th></tr>';
    // Usar 'percentage' consistentemente
    $table .= '<tr style="background-color:#f8f8f8;"><th width="40%">' . get_string('trait', 'block_personality_test') . '</th><th width="30%" align="center">' . get_string('count', 'block_personality_test') . '</th><th width="30%" align="right">' . get_string('percentage', BLOCK_PT_LANG) . '</th></tr></thead>';
    $table .= '<tbody>';
    $table .= '<tr><td width="40%">' . $label1 . '</td><td width="30%" align="center">' . $count1 . '</td><td width="30%" align="right">' . number_format($perc1, 1, ',', '.') . '%</td></tr>';
    $table .= '<tr><td width="40%">' . $label2 . '</td><td width="30%" align="center">' . $count2 . '</td><td width="30%" align="right">' . number_format($perc2, 1, ',', '.') . '%</td></tr>';
    $table .= '</tbody></table>';
    return $table;
}


// Generación y posicionamiento de las 4 tablas de rasgos 
$margen_vertical = 8; // Espacio vertical entre tablas

// Tabla 1
$html1 = crearTablaAspectoHTML(
    'introversion_extroversion',
    'Introvertido',
    $aspect_counts['Introvertido'],
    'Extrovertido',
    $aspect_counts['Extrovertido'],
    $total_students_processed
);
$pdf->writeHTML($html1, true, false, true, false, '');
$pdf->Ln($margen_vertical);

// Tabla 2
$html2 = crearTablaAspectoHTML(
    'sensacion_intuicion',
    'Sensing',
    $aspect_counts['Sensing'],
    'Intuicion',
    $aspect_counts['Intuicion'],
    $total_students_processed
);
$pdf->writeHTML($html2, true, false, true, false, '');
$pdf->Ln($margen_vertical);

// Tabla 3
$html3 = crearTablaAspectoHTML(
    'pensamiento_sentimiento',
    'Pensamiento',
    $aspect_counts['Pensamiento'],
    'Sentimiento',
    $aspect_counts['Sentimiento'],
    $total_students_processed
);
$pdf->writeHTML($html3, true, false, true, false, '');
$pdf->Ln($margen_vertical);

// Tabla 4
$html4 = crearTablaAspectoHTML(
    'juicio_percepcion',
    'Juicio',
    $aspect_counts['Juicio'],
    'Percepcion',
    $aspect_counts['Percepcion'],
    $total_students_processed
);
$pdf->writeHTML($html4, true, false, true, false, '');
    
    // Descripción Tipos de MBTI (solo para reporte agregado/grupal con soporte bilingüe)
    $current_lang = current_language();
    
    $pdf->Ln(10);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    
    if ($current_lang == 'es') {
        $pdf->Cell(0, 10, 'Descripción de tipos de personalidad', 0, 1, 'C');
    } else {
        $pdf->Cell(0, 10, 'Personality Type Descriptions', 0, 1, 'C');
    }
    $pdf->Ln(5);
    
    // Generar descripción bilingüe para cada tipo MBTI
    $pdf->SetFont('helvetica', '', 9);
    foreach ($mbti_types as $type) {
        $mbti_key = 'mbti_' . strtolower($type);
        $mbti_dimensions_key = 'mbti_dimensions_' . strtolower($type);
        
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, $type . ' - ' . get_string($mbti_dimensions_key, 'block_personality_test'), 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(0, 5, get_string($mbti_key, 'block_personality_test'), 0, 'J');
        $pdf->Ln(3);
    }
} // End of aggregated report
// --- SALIDA DEL PDF ---
@ob_end_clean();

if ($is_individual_report && $student_user) {
    // Nombre personalizado para reporte individual usando string de idioma
    $student_name = preg_replace('/[^a-z0-9]/i', '_', strtolower(fullname($student_user)));
    $date_str = date('Y-m-d');
    $filename = get_string('export_filename', 'block_personality_test') . '_' . $student_name . '_' . $student_mbti . '_' . $date_str . '.pdf';
} else {
    // Nombre para reporte agregado usando string de idioma
    $course_name = preg_replace('/[^a-z0-9]/i', '_', strtolower($course->shortname));
    $date_str = date('Y-m-d');
    $filename = get_string('export_filename', 'block_personality_test') . '_' . $course_name . '_' . $date_str . '.pdf';
}
$filename = clean_filename($filename);

$pdf->Output($filename, 'D');
exit; // Terminar script
?>
