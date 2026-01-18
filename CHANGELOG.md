# CHANGELOG

Todas las modificaciones importantes del proyecto se documentarán en este archivo.

## [2.0.4] - 2026-01-18
- Se eliminaron las comprobaciones redundantes de administrador (`is_siteadmin()`) en varias vistas clave, mejorando la detección correcta de roles locales (profesores vs estudiantes) y el sistema de permisos basado en capacidades.

## [2.0.3] — 2026-01-18
- Opción para mostrar/ocultar las descripciones en el bloque principal.
- Adición del gráfico radar en resultados detallados y acceso permitido a los estudiantes a esta vista.
- Se mantiene el titulo "Exploración de Personalidad" en todas las vistas del bloque.
- Se ha eliminado las referencias a la palabra "Test".

## [2.0.2] — 2026-01-08
- Paginación en la lista de estudiantes en el panel de administración.
- Nueva forma de mostrar las estadísticas generales en el panel de administración.
- Uso de arquitectura de plantillas Mustache para todas las vistas del bloque.
- Refactorización completa del código para separar lógica de presentación.
- Mejora en la mantenibilidad y escalabilidad del código.
- Optimización del rendimiento en búsquedas.
- Pequeñas mejoras de seguridad.

## [2.0.1] — 2025-12-22
- Correción de un error (al actualizar de la versión 1.x a 2.0.0) que causaba fallos en la base de datos.
- Lectura correcta del CHANGELOG en el flujo de trabajo de GitHub Actions.

## [2.0.0] — Inicio de registro
- A partir de la versión 2.0.0 se comienza a documentar este CHANGELOG.
- Renovación completa y moderna de la UI/UX del bloque, test, panel de administración y vistas individuales (Todas con diseño responsivo).
- Mejora en la experiencia/flujo de usuario (Profesores y Estudiantes).
- Guardado automático de respuestas y progreso.
- Manejo de empates mejorados.
- Uso de logos institucionales y paleta de colores oficial.
- Soporte para múltiples idiomas (Español e Inglés).
- Consistencia con los otros bloques (chaside, learning_style y tmms_24).
- Seguridad mejorada.
- Optimización del rendimiento.
- Corrección de errores menores.
