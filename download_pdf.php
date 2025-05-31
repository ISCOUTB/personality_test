<?php

/**
 * Descarga los resultados agregados del test de personalidad en formato PDF para un curso.
 *
 * @package     block_personality_test
 * @copyright
 * @license
 */

// --- INCLUDES Y CONFIGURACIÓN INICIAL DE MOODLE ---
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/tcpdf/tcpdf.php');

// --- PARÁMETROS Y SEGURIDAD ---
$courseid = required_param('courseid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

// Moodle globals
global $DB, $USER, $COURSE, $PAGE, $OUTPUT, $CFG; // Añadido $CFG globalmente para usar en Header

// Verifica la clave de sesión
require_sesskey($sesskey);

// Obtiene el curso y el contexto. Termina si no existen.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id);

// Requiere inicio de sesión y capacidad para ver secciones ocultas (típico de profesor/editor)
require_login($course, false);
require_capability('moodle/course:viewhiddensections', $context);

// Define el nombre del plugin para usar en get_string
define('BLOCK_PT_LANG', 'block_personality_test');

// --- OBTENCIÓN Y PROCESAMIENTO DE DATOS ---
$students = $DB->get_records('personality_test', ['course' => $course->id]);

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
        debugging("Omitiendo registro de estudiante ID {$entry->id} por falta de datos.", DEBUG_DEVELOPER);
        continue;
    }

    $mbti_score = "";
    $mbti_score .= ($entry->extraversion >= $entry->introversion) ? "E" : "I";
    $mbti_score .= ($entry->sensing > $entry->intuition) ? "S" : "N";
    $mbti_score .= ($entry->thinking >= $entry->feeling) ? "T" : "F";
    $mbti_score .= ($entry->judging > $entry->perceptive) ? "J" : "P";

    if (isset($mbti_count[$mbti_score])) {
        $mbti_count[$mbti_score]++;
    }

    $aspect_counts['Introvertido'] += ($entry->introversion > $entry->extraversion) ? 1 : 0;
    $aspect_counts['Extrovertido'] += ($entry->extraversion >= $entry->introversion) ? 1 : 0;
    $aspect_counts['Sensing'] += ($entry->sensing > $entry->intuition) ? 1 : 0;
    $aspect_counts['Intuicion'] += ($entry->intuition >= $entry->sensing) ? 1 : 0;
    $aspect_counts['Pensamiento'] += ($entry->thinking > $entry->feeling) ? 1 : 0;
    $aspect_counts['Sentimiento'] += ($entry->feeling >= $entry->thinking) ? 1 : 0;
    $aspect_counts['Juicio'] += ($entry->judging > $entry->perceptive) ? 1 : 0;
    $aspect_counts['Percepcion'] += ($entry->perceptive >= $entry->judging) ? 1 : 0;

    $total_students_processed++;
}

$mbti_count_filtered = $mbti_count;

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


// Descripción Tipos de MBTI
$descripcion_html = '
<h2>Descripción de tipos de personalidad</h2>
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr style="background-color:#eeeeee; font-weight:bold;">
            <th>Tipo</th>
            <th>Descripción</th>
        </tr>
    </thead>
    <tbody>
  <tr><td>ISTJ</td><td>Introvertido, Sensitivo, Pensamiento, Juicio</td></tr>
  <tr><td>ISFJ</td><td>Introvertido, Sensitivo, Sentimiento, Juicio</td></tr>
  <tr><td>INFJ</td><td>Introvertido, Intuitivo, Sentimiento, Juicio</td></tr>
  <tr><td>INTJ</td><td>Introvertido, Intuitivo, Pensamiento, Juicio</td></tr>
  <tr><td>ISTP</td><td>Introvertido, Sensitivo, Pensamiento, Percepción</td></tr>
  <tr><td>ISFP</td><td>Introvertido, Sensitivo, Sentimiento, Percepción</td></tr>
  <tr><td>INFP</td><td>Introvertido, Intuitivo, Sentimiento, Percepción</td></tr>
  <tr><td>INTP</td><td>Introvertido, Intuitivo, Pensamiento, Percepción</td></tr>
  <tr><td>ESTP</td><td>Extrovertido, Sensitivo, Pensamiento, Percepción</td></tr>
  <tr><td>ESFP</td><td>Extrovertido, Sensitivo, Sentimiento, Percepción</td></tr>
  <tr><td>ENFP</td><td>Extrovertido, Intuitivo, Sentimiento, Percepción</td></tr>
  <tr><td>ENTP</td><td>Extrovertido, Intuitivo, Pensamiento, Percepción</td></tr>
  <tr><td>ESTJ</td><td>Extrovertido, Sensitivo, Pensamiento, Juicio</td></tr>
  <tr><td>ESFJ</td><td>Extrovertido, Sensitivo, Sentimiento, Juicio</td></tr>
  <tr><td>ENFJ</td><td>Extrovertido, Intuitivo, Sentimiento, Juicio</td></tr>
  <tr><td>ENTJ</td><td>Extrovertido, Intuitivo, Pensamiento, Juicio</td></tr>
</tbody>

</table>
';

$pdf->Ln(10); 
$pdf->writeHTML($descripcion_html, true, false, true, false, '');

// --- SALIDA DEL PDF ---
@ob_end_clean();

$filename = 'resultados_personalidad_' . preg_replace('/[^a-z0-9]/i', '_', $course->shortname) . '_' . date('Ymd') . '.pdf';
$filename = clean_filename($filename);

$pdf->Output($filename, 'D');
exit; // Terminar script
?>

