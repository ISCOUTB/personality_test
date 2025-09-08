<?php
/**
 * Test del sistema de releases para personality_test
 * Uso: /blocks/personality_test/test_release_system.php
 */

require_once('../../config.php');

require_login();

// Solo administradores pueden ver esta página
require_capability('moodle/site:config', context_system::instance());

// Configurar página
$PAGE->set_url('/blocks/personality_test/test_release_system.php');
$PAGE->set_title('Test del Sistema de Releases - Personality Test');
$PAGE->set_heading('Test del Sistema de Releases - Personality Test');
$PAGE->set_context(context_system::instance());

echo $OUTPUT->header();

echo '<div style="max-width: 900px; margin: 20px auto; padding: 20px; background: #f9f9f9; border: 1px solid #ddd;">';
echo '<h2>🚀 Sistema de Releases - Personality Test</h2>';

// Leer información de versión
$version_file = $CFG->dirroot . '/blocks/personality_test/version.php';
if (file_exists($version_file)) {
    $version_content = file_get_contents($version_file);
    
    // Extraer versión y release
    preg_match('/version\s*=\s*(\d+)/', $version_content, $version_matches);
    preg_match('/release\s*=\s*[\'"]([^\'"]+)[\'"]/', $version_content, $release_matches);
    
    $plugin_version = isset($version_matches[1]) ? $version_matches[1] : 'No encontrado';
    $plugin_release = isset($release_matches[1]) ? $release_matches[1] : 'No encontrado';
} else {
    $plugin_version = 'Archivo no encontrado';
    $plugin_release = 'Archivo no encontrado';
}

// Información actual del plugin
echo '<h3>📋 Información del Plugin:</h3>';
echo '<div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">';
echo '<ul>';
echo '<li><strong>Versión del Plugin:</strong> ' . $plugin_version . '</li>';
echo '<li><strong>Release:</strong> ' . $plugin_release . '</li>';
echo '<li><strong>Componente:</strong> block_personality_test</li>';
echo '<li><strong>Directorio:</strong> ' . $CFG->dirroot . '/blocks/personality_test/</li>';
echo '</ul>';
echo '</div>';

// Verificar archivos de GitHub Actions
$github_dir = $CFG->dirroot . '/blocks/personality_test/.github/workflows/';
$release_workflow = $github_dir . 'release.yml';
$build_workflow = $github_dir . 'build.yml';

echo '<h3>⚙️ Estado de GitHub Actions:</h3>';
echo '<div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">';
echo '<ul>';
echo '<li><strong>Directorio .github/workflows:</strong> ' . (is_dir($github_dir) ? '✅ Existe' : '❌ No existe') . '</li>';
echo '<li><strong>release.yml:</strong> ' . (file_exists($release_workflow) ? '✅ Existe' : '❌ No existe') . '</li>';
echo '<li><strong>build.yml:</strong> ' . (file_exists($build_workflow) ? '✅ Existe' : '❌ No existe') . '</li>';
echo '</ul>';
echo '</div>';

// Verificar archivos de documentación
$readme_file = $CFG->dirroot . '/blocks/personality_test/README.md';
$releases_file = $CFG->dirroot . '/blocks/personality_test/RELEASES.md';
$gitignore_file = $CFG->dirroot . '/blocks/personality_test/.gitignore';

echo '<h3>📝 Documentación:</h3>';
echo '<div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">';
echo '<ul>';
echo '<li><strong>README.md:</strong> ' . (file_exists($readme_file) ? '✅ Existe' : '❌ No existe') . '</li>';
echo '<li><strong>RELEASES.md:</strong> ' . (file_exists($releases_file) ? '✅ Existe' : '❌ No existe') . '</li>';
echo '<li><strong>.gitignore:</strong> ' . (file_exists($gitignore_file) ? '✅ Existe' : '❌ No existe') . '</li>';
echo '</ul>';
echo '</div>';

// Simular el proceso de release
echo '<h3>🔄 Simulación del Proceso de Release:</h3>';
echo '<div style="background: #e8f5e8; padding: 15px; border: 1px solid #4caf50; border-radius: 5px; margin: 10px 0;">';
echo '<h4>Cuando hagas push a la rama main:</h4>';
echo '<ol>';
echo '<li>🔍 GitHub Actions detectará cambios en la versión</li>';
echo '<li>📦 Creará un paquete ZIP con todos los archivos</li>';
echo '<li>🏷️ Generará un tag automáticamente (v' . $plugin_release . ')</li>';
echo '<li>📋 Creará un release en GitHub con notas detalladas</li>';
echo '<li>⬇️ El ZIP estará disponible para descarga</li>';
echo '</ol>';
echo '</div>';

// Siguiente tag esperado
echo '<h3>🏷️ Próximo Release:</h3>';
echo '<div style="background: #fff3cd; padding: 15px; border: 1px solid #ffc107; border-radius: 5px; margin: 10px 0;">';
echo '<p><strong>Tag que se creará:</strong> <code>v' . $plugin_release . '</code></p>';
echo '<p><strong>Archivo ZIP:</strong> <code>block_personality_test_v' . $plugin_release . '.zip</code></p>';
echo '<p><strong>Para crear un nuevo release:</strong></p>';
echo '<ol>';
echo '<li>Actualiza <code>$plugin->version</code> y <code>$plugin->release</code> en version.php</li>';
echo '<li>Haz commit y push a la rama main</li>';
echo '<li>GitHub Actions se encargará del resto automáticamente</li>';
echo '</ol>';
echo '</div>';

// Características del sistema
echo '<h3>✨ Características del Sistema de Releases:</h3>';
echo '<div style="background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;">';
echo '<ul>';
echo '<li>🤖 <strong>Completamente automático</strong>: Solo necesitas actualizar la versión</li>';
echo '<li>📦 <strong>Packages listos para instalar</strong>: ZIP directo para Moodle</li>';
echo '<li>📝 <strong>Release notes automáticas</strong>: Con instrucciones de instalación</li>';
echo '<li>🔢 <strong>Versionado semántico</strong>: Sigue las mejores prácticas</li>';
echo '<li>🏷️ <strong>Tags automáticos</strong>: Git tags creados automáticamente</li>';
echo '<li>🔄 <strong>Builds de desarrollo</strong>: Para testing antes del release</li>';
echo '<li>📊 <strong>Badges de estado</strong>: En el README para mostrar el estado</li>';
echo '</ul>';
echo '</div>';

// Comparación con student_path
echo '<h3>🔗 Comparación con student_path:</h3>';
$student_path_version_file = $CFG->dirroot . '/blocks/student_path/version.php';
if (file_exists($student_path_version_file)) {
    $sp_content = file_get_contents($student_path_version_file);
    preg_match('/version\s*=\s*(\d+)/', $sp_content, $sp_version_matches);
    preg_match('/release\s*=\s*[\'"]([^\'"]+)[\'"]/', $sp_content, $sp_release_matches);
    
    $sp_version = isset($sp_version_matches[1]) ? $sp_version_matches[1] : 'No encontrado';
    $sp_release = isset($sp_release_matches[1]) ? $sp_release_matches[1] : 'No encontrado';
    
    echo '<div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">';
    echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
    
    echo '<div>';
    echo '<h4>Student Path</h4>';
    echo '<ul>';
    echo '<li><strong>Versión:</strong> ' . $sp_version . '</li>';
    echo '<li><strong>Release:</strong> ' . $sp_release . '</li>';
    echo '<li><strong>Estado:</strong> ✅ Sistema implementado</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '<div>';
    echo '<h4>Personality Test</h4>';
    echo '<ul>';
    echo '<li><strong>Versión:</strong> ' . $plugin_version . '</li>';
    echo '<li><strong>Release:</strong> ' . $plugin_release . '</li>';
    echo '<li><strong>Estado:</strong> ✅ Sistema implementado</li>';
    echo '</ul>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
} else {
    echo '<div style="background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;">';
    echo 'No se pudo encontrar el archivo version.php de student_path para comparar.';
    echo '</div>';
}

// Enlaces útiles
echo '<h3>🔗 Enlaces Útiles:</h3>';
echo '<div style="background: white; padding: 15px; border: 1px solid #ddd; border-radius: 5px; margin: 10px 0;">';
echo '<ul>';
echo '<li><a href="' . $CFG->wwwroot . '/blocks/personality_test/README.md" target="_blank">README del proyecto</a></li>';
echo '<li><a href="' . $CFG->wwwroot . '/blocks/personality_test/RELEASES.md" target="_blank">Documentación de releases</a></li>';
echo '<li><a href="https://github.com/ISCOUTB/personality_test" target="_blank">Repositorio en GitHub</a></li>';
echo '<li><a href="https://github.com/ISCOUTB/personality_test/releases" target="_blank">Releases en GitHub</a></li>';
echo '<li><a href="https://github.com/ISCOUTB/personality_test/actions" target="_blank">GitHub Actions</a></li>';
echo '</ul>';
echo '</div>';

echo '</div>';

echo $OUTPUT->footer();
?>
