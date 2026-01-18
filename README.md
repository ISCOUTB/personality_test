# Bloque Exploración de Personalidad (Moodle)

El bloque **Test de Personalidad** permite a estudiantes realizar una prueba de **72 preguntas (Sí/No)** y obtener un perfil tipo **MBTI** (4 ejes: **Extraversión/Introversión**, **Sensorial/Intuitivo**, **Pensamiento/Sentimiento**, **Juicio/Percepción**). Para docentes y administradores, incorpora vistas de seguimiento, métricas agregadas, panel administrativo y exportación de reportes.

Este repositorio incluye:
- Experiencia de estudiante con **guardado progresivo**, validaciones por página y reanudación.
- Herramientas docentes con **dashboard embebido**, **panel de administración**, **vista individual** y **exportación CSV/PDF**.

## Contenido

- [Funcionalidades](#funcionalidades)
- [Recorrido Visual](#recorrido-visual)
- [Sección técnica (modelo de datos, cálculo, flujos, permisos, endpoints)](#sección-técnica)
- [Instalación](#instalación)
- [Operación y soporte](#operación-y-soporte)
- [Contribuciones](#contribuciones)
- [Equipo de desarrollo](#equipo-de-desarrollo)

---

## Funcionalidades

### Para estudiantes
- **Aplicación del test** (72 ítems, Sí/No) distribuido en **8 páginas** (9 preguntas por página).
- **Guardado progresivo** con autosave tras inactividad y reanudación desde la primera pregunta pendiente.
- **Validación por página** antes de avanzar o finalizar.
- **Resultados** con tipo MBTI (p. ej. `ENTJ`) y visualizaciones comparativas por dimensión.

### Para docentes / administradores
- **Vista del bloque** con:
  - Distribución de tipos MBTI (gráfico de torta),
  - Distribución por rasgos (E/I, S/N, T/F, J/P) en gráficos de barras,
  - Accesos rápidos a descargas.
- **Panel de administración** con:
  - **Conteos** (matriculados, completados, en progreso, tasa de finalización),
  - **Estadísticas Generales** (Top 4 de Tipos de Personalidad más comunes y Promedios por dimensión).
  - **Tabla de participantes** (nombre, correo, estado, tipo MBTI).
  - Acceso a **vista individual** por estudiante.
  - Posibilidad de **eliminación** de resultados individuales.
  - Exportación **CSV** y **PDF** agregados por curso.
- Opción para **mostrar/ocultar** las descripciones en el bloque principal **(oculto por defecto)**.
- **Controles de privacidad**: acceso restringido por capacidades, matrícula y (en vista individual) reglas de grupo.

---

## Recorrido Visual

### 1. Experiencia del Estudiante

**Acceso Intuitivo y Llamado a la Acción**

El recorrido comienza con una invitación clara y directa. Desde el bloque principal del curso, el estudiante puede visualizar su estado actual y acceder al test con un solo click, facilitando la participación sin fricciones.
<p align="center">
  <img src="https://github.com/user-attachments/assets/c7ba3e79-0ce2-422c-b472-5d7140c87ae9" alt="Invitación al Test" width="528">
</p>

**Interfaz de Evaluación Optimizada**

Se presenta un entorno de respuesta limpio y libre de distracciones. La interfaz ha sido diseñada para priorizar la legibilidad y la facilidad de uso, permitiendo que el estudiante se concentre totalmente en el proceso de autodescubrimiento.
<p align="center">
  <img src="https://github.com/user-attachments/assets/a2ad9fda-b5f6-4ae5-8e37-322cb649934a" alt="Formulario del Test" width="528">
</p>

**Asistencia y Validación en Tiempo Real**

Para garantizar la integridad de los datos, el sistema implementa una validación inteligente. Si el usuario olvida alguna respuesta, el sistema lo guía visualmente mediante alertas en rojo y un desplazamiento automático hacia los campos pendientes, asegurando una experiencia sin errores.

<p align="center">
  <img src="https://github.com/user-attachments/assets/d320f9ab-9b6e-4448-b0a0-9b9cd57118ba" alt="Validación" width="528">
</p>

**Persistencia de Progreso y Continuidad**

Entendemos que el tiempo es valioso. Si el estudiante debe interrumpir su sesión, el sistema guarda automáticamente su avance. Al regresar, el bloque muestra el porcentaje de progreso y permite reanudar el test exactamente donde se dejó, resaltando visualmente la siguiente pregunta a responder.
	
<p align="center">
  <img src="https://github.com/user-attachments/assets/e3b7f7e2-3085-4d42-8d84-48011ef8557c" alt="Progreso del Test" height="350">
  &nbsp;&nbsp;
  <img src="https://github.com/user-attachments/assets/002b5f98-beee-453e-a044-1454d05130c8" alt="Continuar Test" height="350">
</p>

**Confirmación de Envío Pendiente**
Si el estudiante ha completado las 44 preguntas pero aún no ha procesado el envío, el bloque muestra una notificación clara y amigable, invitándolo a formalizar la entrega y conocer su tipo de personalidad.

<p align="center">
  <img src="https://github.com/user-attachments/assets/15915dfa-79ec-40f8-9d7d-9907e81ea15b" alt="Confirmación de Test Completado" width="528">
</p>

**Análisis de Perfil y Recomendaciones Personalizadas**

Al finalizar, el estudiante recibe una tarjeta donde puede ver su tipo MBTI con lo que signfica cada letra, junto con un acceso directo a sus resultados detallados donde podrá ver gráficos y la descripción de su tipo de personalidad.

<p align="center">
  <img src="https://github.com/user-attachments/assets/8e834577-93e5-46d2-be52-46411c27db71" alt="Resultados del Estudiante" width="528">
</p>

<p align="center">
  <img src="https://github.com/user-attachments/assets/1f7ac7d7-0073-4e6c-b6b5-444bfc88ed45" alt="Vista Detallada del Estudiante" width="600">
</p>

### 2. Experiencia del Profesor

**Dashboard de Control Rápido (Vista del Bloque)**

El profesor cuenta con una vista ejecutiva desde el bloque, donde puede monitorizar métricas clave y gráficos de tendencia de forma inmediata, además de acceder a funciones avanzadas de exportación y administración.

<p align="center">
  <img src="https://github.com/user-attachments/assets/f3f8a30e-ac03-4657-b776-7052f09c2c6d" alt="Bloque del Profesor" width="528">
</p>

**Centro de Gestión y Analíticas**

Un panel de administración que centraliza el seguimiento grupal. Permite visualizar quiénes han completado el proceso, quiénes están en curso y gestionar los resultados colectivos para adaptar la estrategia pedagógica del aula.

<p align="center">
  <img src="https://github.com/user-attachments/assets/7b014f1b-91d8-4ced-84e7-e89cd49d16d8" alt="Panel de Administración" width="800">
</p>

**Seguimiento Individualizado y Detallado**

El docente puede profundizar en el perfil específico de cada estudiante. Esta vista permite conocer el tipo de personalidad y su descripción detallada.

- **Nota:** Esta vista es la misma que la del estudiante, pero accesible por el profesor para cualquier alumno del curso.
---


## Sección técnica

Esta sección describe el comportamiento **tal como está implementado** en el bloque (cálculo, persistencia, flujos y controles de acceso).

### 1) Estructura del test y codificación de respuestas

- Total de preguntas: **72**.
- Opciones por pregunta: **Sí/No**.
- Paginación: **9 preguntas por página** (**8** páginas).
- Persistencia en base de datos:
  - Se almacena una columna por pregunta: `q1` … `q72`.
  - Valores: **Sí = 1**, **No = 0**.

### 2) Mapeo de preguntas a dimensiones

El cálculo usa 8 conjuntos de índices (9 ítems por dimensión), definidos en el guardado final:

- **Extraversion (E)**: 5, 7, 10, 13, 23, 25, 61, 68, 71
- **Introversion (I)**: 2, 9, 49, 54, 63, 65, 67, 69, 72
- **Sensing (S)**: 15, 43, 45, 51, 53, 56, 59, 66, 70
- **Intuition (N)**: 37, 39, 41, 44, 47, 52, 57, 62, 64
- **Thinking (T)**: 1, 4, 6, 18, 20, 48, 50, 55, 58
- **Feeling (F)**: 3, 8, 11, 14, 27, 31, 33, 35, 40
- **Judging (J)**: 19, 21, 24, 26, 29, 34, 36, 42, 46
- **Perceiving (P)**: 12, 16, 17, 22, 28, 30, 32, 38, 60

### 3) Cálculo de puntajes y tipo MBTI

El puntaje por dimensión es la **suma** de las respuestas (Sí=1/No=0) en el conjunto correspondiente, por lo que cada dimensión queda en un rango **0–9**.

Se guardan 8 totales en la tabla:

- `extraversion`, `introversion`
- `sensing`, `intuition`
- `thinking`, `feeling`
- `judging`, `perceptive`

Derivación de tipo MBTI (comparación por pares):

Se aplica la **Regla de "Prevalencia Teórica"**: ante un empate, se favorece una de las dos polaridades basándose en la teoría del autor.

- **E/I**: `I` si `extraversion == introversion` (Se asigna I).
- **S/N**: `N` si `sensing == intuition` (Se asigna N).
- **T/F**: `T` si `thinking == feeling` (Se asigna T).
- **J/P**: `P` si `judging == perceptive` (Se asigna P).

Implementación técnica:

- **E/I**: `E` si `extraversion > introversion`, si no `I`
- **S/N**: `S` si `sensing > intuition`, si no `N`
- **T/F**: `T` si `thinking >= feeling`, si no `F`
- **J/P**: `J` si `judging > perceptive`, si no `P`

> Nota: Todas las vistas (bloque, reportes, exportaciones) han sido unificadas para seguir estas reglas de desempate.

*Impacto:* Si un estudiante queda **exactamente empatado** en un eje, el sistema asignará consistentemente la letra definida por la regla de prevalencia (I, N, T, P).

### 4) Guardado progresivo, navegación y reanudación

El flujo del estudiante está diseñado para soportar progreso parcial:

- Se crea/actualiza un registro con `is_completed = 0` mientras el test está en curso.
- El autosave se ejecuta tras **400 milisegundos** de inactividad después de responder.
- La navegación **Anterior/Siguiente** guarda progreso en el servidor.

Reglas de integridad implementadas:

- **No se puede finalizar** si falta alguna respuesta: el servidor valida las 72 preguntas y redirige a la página de la primera pendiente.
- **No se puede saltar páginas**: el servidor calcula una “página máxima permitida” en base al progreso guardado.
- **Guardado robusto**: el endpoint de guardado maneja condición de carrera (insert/update concurrente) unificando respuestas existentes con las nuevas.

### 5) Modelo de datos (tabla principal)

Tabla: `personality_test`

- `user` (índice **único**): el test se almacena **globalmente por usuario**.
- `is_completed`: 0 (en progreso) / 1 (completado).
- `q1..q72`: respuestas individuales.
- Totales: `extraversion`, `introversion`, `sensing`, `intuition`, `thinking`, `feeling`, `judging`, `perceptive`.
- Trazabilidad: `created_at`, `updated_at` y `last_action`.

Implicación importante:

- Al ser **único por usuario** y sin campo de curso, el resultado puede reutilizarse entre cursos. Las vistas docentes filtran participantes por **matrícula en el curso**, pero el registro del test pertenece al usuario a nivel global.

### 6) Vistas, endpoints y exportación

**Estudiante**

- Formulario del test: `view.php?cid=<courseid>`
- Endpoint de guardado (autosave / navegación / finish): `save.php` (POST con `sesskey`)

**Docente / Administrador**

- Panel de administración del curso: `admin_view.php?cid=<courseid>`
- Vista individual: `view_individual.php?cid=<courseid>&userid=<userid>`
- Exportación CSV agregada: `download_csv.php?cid=<courseid>&sesskey=<sesskey>`
- Exportación PDF agregada/individual: `download_pdf.php?cid=<courseid>[&userid=<userid>][&sesskey=<sesskey>]`

### 7) Permisos (capabilities) y controles de acceso

El bloque define capacidades específicas:

- `block/personality_test:taketest` (estudiante): permite tomar el test.
- `block/personality_test:viewreports` (docente/manager): permite ver reportes, paneles y exportaciones.
- `block/personality_test:addinstance` / `block/personality_test:myaddinstance`: gestión de instancias del bloque.

Controles adicionales implementados:

- Exportaciones y panel administrativo requieren `viewreports` (o ser site admin).
- La tabla de participantes excluye usuarios con capacidad de reportes (defensa ante mala configuración de roles).
- La vista individual aplica restricciones de grupo cuando el docente no tiene `moodle/site:accessallgroups`.

---

## Instalación

1. Descargar el plugin desde las *releases* del repositorio oficial: https://github.com/ISCOUTB/personality_test/
2. En Moodle (como administrador):
   - Ir a **Administración del sitio → Extensiones → Instalar plugins**.
   - Subir el archivo ZIP.
   - Completar el asistente de instalación.
3. En un curso, agregar el bloque **Exploración de Personalidad** desde el selector de bloques.

---

## Operación y soporte

### Consideraciones de despliegue

- Compatibilidad declarada: Moodle **4.0+**.
- Gráficas: el bloque consume `core/chartjs` (Chart.js provisto por Moodle).
- PDF: se genera con **TCPDF** (incluido en Moodle vía `$CFG->libdir/tcpdf`).

### Resolución de problemas (rápido)

- **El estudiante no ve el test**: validar que tenga la capacidad `block/personality_test:taketest` en el contexto del curso.
- **El docente no ve reportes/descargas**: validar `block/personality_test:viewreports`.
- **El progreso no se guarda**: revisar que el POST incluya `sesskey` válido y que no haya bloqueos del navegador/red.

---

## Contribuciones

¡Las contribuciones son bienvenidas! Si deseas mejorar este bloque, por favor sigue estos pasos:

1. Haz un fork del repositorio.
2. Crea una nueva rama para tu característica o corrección de errores.
3. Realiza tus cambios y asegúrate de que todo funcione correctamente.
4. Envía un pull request describiendo tus cambios.

---

## Equipo de desarrollo

- Jairo Enrique Serrano Castañeda
- Yuranis Henriquez Núñez
- Isaac David Sánchez Sánchez
- Santiago Andrés Orejuela Cueter
- María Valentina Serna González

<div align="center">
<strong>Desarrollado con ❤️ para la Universidad Tecnológica de Bolívar</strong>
</div>
