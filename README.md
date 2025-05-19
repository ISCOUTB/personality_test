# Bloque Personality Test para Moodle

## Descripción General

El bloque `personality_test` permite a los estudiantes de un curso realizar un test de personalidad tipo MBTI y visualizar sus resultados, mientras que los profesores pueden ver estadísticas agregadas y exportar los datos en formatos CSV y PDF. El bloque es completamente internacionalizable, responsivo y sigue las buenas prácticas de desarrollo para Moodle.

---

## Estructura de Archivos

```
personality_test/
│
├── amd/
│   ├── src/
│   │   └── charts.js         # Lógica JS para gráficas (Chart.js, AMD)
│   └── build/
│       └── charts.min.js     # Versión minificada del JS
│
├── db/
│   ├── access.php            # Definición de capacidades y permisos
│   └── install.xml           # Estructura de la base de datos
│
├── lang/
│   ├── es/
│   │   └── block_personality_test.php  # Idioma español
│   └── en/
│       └── block_personality_test.php  # Idioma inglés
│
├── pix/                      # Imágenes e íconos del bloque
│
├── block_personality_test.php # Lógica principal del bloque (PHP, usa patrón Facade)
├── styles.css                # Estilos CSS, responsivo y adaptado a SAVIO UTB
├── view.php                  # Vista del formulario del test para estudiantes
├── save.php                  # Lógica de guardado de respuestas
├── lib.php                   # Funciones auxiliares (guardar resultados)
├── download_csv.php          # Exportación profesional de resultados en CSV
├── download_pdf.php          # Exportación profesional de resultados en PDF
├── edit_form.php             # Formulario de edición/configuración del bloque
├── version.php               # Versión y metadatos del plugin
└── README.md                 # Documentación básica y créditos
```

---

## Principales Componentes y Responsabilidades

- **block_personality_test.php**  
  - Controlador principal del bloque.
  - Usa el patrón **Facade** para separar la lógica de negocio (cálculo MBTI, conteos, explicaciones).
  - Presenta diferentes vistas según el rol (estudiante, profesor, otros).
  - Llama a los módulos JS para mostrar gráficas.

- **PersonalityTestFacade (en block_personality_test.php)**  
  - Encapsula la lógica de negocio: cálculo de tipo MBTI, explicaciones, conteos de tipos y rasgos.
  - Facilita la mantenibilidad y escalabilidad.

- **charts.js (AMD)**  
  - Renderiza las gráficas usando Chart.js.
  - Recibe datos desde PHP y los muestra de forma responsiva y profesional.
  - Fácil de modificar para cambiar tipos de gráficas o librería.

- **save.php / lib.php**  
  - Procesan y guardan las respuestas del test en la base de datos.
  - Validan y aseguran la integridad de los datos.

- **download_csv.php / download_pdf.php**  
  - Exportan los resultados de forma profesional, con metadatos y estructura clara.

- **styles.css**  
  - Estilos modernos, responsivos y adaptados a la identidad visual de SAVIO UTB.

- **Archivos de idioma**  
  - Permiten la traducción completa del bloque.

---

## Buenas Prácticas y Estándares Cumplidos

- **Internacionalización**: Todos los textos están en archivos de idioma.
- **Seguridad**: Uso de permisos, validación de parámetros, y control de acceso.
- **Separación de responsabilidades**: Lógica de negocio separada de la presentación.
- **Responsividad**: CSS y JS adaptados a cualquier dispositivo y nivel de zoom.
- **Extensibilidad**: Fácil de modificar o ampliar (por ejemplo, cambiar gráficas).
- **Compatibilidad Moodle**: Uso de AMD para JS, helpers de Moodle para HTML, y API de base de datos.

---

## Evaluación ATAM (Architecture Tradeoff Analysis Method)

### A. Atributos de Calidad Evaluados
- **Mantenibilidad**
- **Escalabilidad**
- **Seguridad**
- **Internacionalización**
- **Usabilidad**
- **Rendimiento**
- **Extensibilidad**

### B. Riesgos Identificados
- **Dependencia de Chart.js**: Si se quiere cambiar la librería de gráficas, hay que modificar el JS, pero la separación actual lo facilita.
- **Crecimiento de lógica de negocio**: Si la lógica de personalidad crece mucho, podría ser necesario migrar la fachada a un archivo propio o incluso a un servicio.
- **Validación de datos**: Si se agregan más tipos de tests, habría que generalizar la lógica de guardado y cálculo.

### C. Trade-offs (Compromisos)
- **Simplicidad vs. Escalabilidad**:  
  El uso de una fachada y separación de JS añade un poco de complejidad inicial, pero permite escalar y mantener el bloque fácilmente en el futuro.
- **Flexibilidad vs. Rendimiento**:  
  El uso de Chart.js y renderizado en el cliente es flexible y visualmente atractivo, pero puede ser menos eficiente con grandes volúmenes de datos (no es un problema en el contexto actual).
- **Internacionalización vs. Facilidad de edición**:  
  Todo texto está en archivos de idioma, lo que es excelente para traducción, pero requiere editar varios archivos para cambios de texto.

### D. Escenarios de Cambio y Facilidad de Adaptación
- **Cambiar el tipo de gráficas**:  
  Solo se modifica el archivo `charts.js` (y su minificado). No afecta la lógica de negocio ni la base de datos.
- **Agregar nuevos idiomas**:  
  Solo se agregan archivos en la carpeta `lang/`.
- **Modificar la lógica de cálculo**:  
  Solo se modifica la clase `PersonalityTestFacade`.
- **Agregar nuevos tipos de test**:  
  Requiere ampliar la base de datos y la fachada, pero la estructura modular lo facilita.

### E. Conclusión ATAM
- **El diseño actual es robusto, seguro y preparado para el crecimiento.**
- **Los riesgos son bajos y los trade-offs están bien balanceados para el contexto educativo de Moodle.**
- **La arquitectura favorece la mantenibilidad, la internacionalización y la experiencia de usuario.**

---

## Recomendaciones Futuras

- Si el bloque crece mucho, migrar la fachada a un archivo/clase independiente.
- Considerar agregar pruebas automáticas (PHPUnit, QUnit).
- Documentar con más detalle las funciones auxiliares en `lib.php`.
- Si se agregan más tipos de test, generalizar la lógica de guardado y cálculo.

---

## Créditos y Contacto

Desarrollado para la plataforma SAVIO UTB, siguiendo estándares de calidad y buenas prácticas de Moodle.
