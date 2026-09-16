USE capacitacion_db;

INSERT INTO capacitaciones (
  id,
  titulo,
  fecha,
  duracion,
  sede,
  programa,
  descripcion,
  instructor,
  objetivos,
  temario,
  habilidades,
  instructor_bio,
  requisitos,
  estado,
  activo
)
SELECT *
FROM (
  SELECT
    1 AS id,
    'Estrategias de programacion web en el aula' AS titulo,
    '2026-09-18' AS fecha,
    '8 horas' AS duracion,
    'Aula Virtual' AS sede,
    'Diseño y Desarrollo de Software' AS programa,
    'Capacitacion orientada a fortalecer la enseñanza de HTML, CSS, JavaScript y buenas practicas de codigo en proyectos formativos.' AS descripcion,
    'Mg. Carlos Rivera' AS instructor,
    '["Planificar sesiones practicas de programacion web","Aplicar buenas practicas de estructura y validacion","Disenar actividades alineadas a proyectos reales"]' AS objetivos,
    '["Diagnostico de competencias digitales","Estructura de una clase practica","Revision guiada de codigo","Retroalimentacion y cierre"]' AS temario,
    '["Programacion web","Didactica digital","Evaluacion practica"]' AS habilidades,
    'Especialista en desarrollo web y metodologias activas para carreras tecnologicas.' AS instructor_bio,
    'Conocimientos basicos de HTML, CSS y JavaScript.' AS requisitos,
    'activa' AS estado,
    1 AS activo
  UNION ALL
  SELECT
    2,
    'Evaluacion por competencias en entornos digitales',
    '2026-09-25',
    '6 horas',
    'Lima Centro',
    'Administracion',
    'Taller para disenar rubricas, evidencias y criterios de evaluacion aplicables a clases presenciales y virtuales.',
    'Lic. Patricia Salazar',
    '["Construir rubricas claras","Relacionar competencias con evidencias","Usar retroalimentacion formativa"]',
    '["Resultados de aprendizaje","Criterios e indicadores","Rubricas y evidencias","Retroalimentacion efectiva"]',
    '["Evaluacion","Rubricas","Retroalimentacion"]',
    'Docente formadora con experiencia en diseño curricular y evaluacion por competencias.',
    'Traer una actividad o evaluacion usada actualmente.',
    'activa',
    1
  UNION ALL
  SELECT
    3,
    'Herramientas de IA para la practica docente',
    '2026-10-02',
    '10 horas',
    'Aula Virtual',
    'Marketing',
    'Sesion practica sobre uso responsable de herramientas de IA para preparar materiales, actividades y retroalimentacion docente.',
    'Ing. Daniela Medina',
    '["Identificar usos pedagogicos de IA","Crear materiales de apoyo","Aplicar criterios de uso responsable"]',
    '["Panorama de herramientas","Prompts para docentes","Revision de sesgos","Actividad aplicada"]',
    '["IA educativa","Diseño de materiales","Uso responsable"]',
    'Consultora en tecnologia educativa y transformacion digital.',
    'No requiere experiencia previa con IA.',
    'activa',
    1
) AS semillas
WHERE NOT EXISTS (
  SELECT 1 FROM capacitaciones
);

ALTER TABLE capacitaciones AUTO_INCREMENT = 4;
