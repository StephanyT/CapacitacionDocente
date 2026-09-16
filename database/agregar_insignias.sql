USE capacitacion_db;

-- Catalogo fijo de 12 insignias (igual que el catalogo de programas/sedes,
-- esto no lo crea el admin desde un formulario — es fijo, como las
-- micro-credenciales de un sistema real). Cada una se liga a una
-- capacitacion real por titulo (la unica que no se liga a ninguna es
-- "Mentor Certus", que se gana dando sesiones de mentoria).
INSERT INTO insignias (nombre, icono, area, requiere_capacitacion, evidencia, requisito)
SELECT * FROM (
  SELECT
    'Desarrollador Educativo' AS nombre, 'AD' AS icono, 'Diseño y Desarrollo de Software' AS area,
    (SELECT id FROM capacitaciones WHERE titulo = 'Introduccion a bases de datos para docentes' LIMIT 1) AS requiere_capacitacion,
    0 AS evidencia,
    'Completa "Introduccion a bases de datos para docentes".' AS requisito
  UNION ALL
  SELECT
    'Arquitecto de Soluciones', 'AC', 'Diseño y Desarrollo de Software',
    (SELECT id FROM capacitaciones WHERE titulo = 'Desarrollo de aplicaciones moviles en el aula' LIMIT 1),
    1,
    'Completa "Desarrollo de aplicaciones moviles en el aula" y envia evidencia de un proyecto aplicado en tu clase.'
  UNION ALL
  SELECT
    'Innovador Digital', 'ID', 'Diseño y Desarrollo de Software',
    (SELECT id FROM capacitaciones WHERE titulo = 'Ciberseguridad basica para instituciones educativas' LIMIT 1),
    0,
    'Completa "Ciberseguridad basica para instituciones educativas".'
  UNION ALL
  SELECT
    'Especialista en Evaluacion', 'EE', 'Administracion',
    (SELECT id FROM capacitaciones WHERE titulo = 'Gestion de proyectos educativos con metodologias agiles' LIMIT 1),
    0,
    'Completa "Gestion de proyectos educativos con metodologias agiles".'
  UNION ALL
  SELECT
    'Lider de Equipo', 'FA', 'Administracion',
    (SELECT id FROM capacitaciones WHERE titulo = 'Liderazgo y gestion de equipos docentes' LIMIT 1),
    1,
    'Completa "Liderazgo y gestion de equipos docentes" y envia evidencia de como lideraste a tu equipo.'
  UNION ALL
  SELECT
    'Estratega de Marca Docente', 'IA', 'Marketing',
    (SELECT id FROM capacitaciones WHERE titulo = 'Branding personal para docentes' LIMIT 1),
    1,
    'Completa "Branding personal para docentes" y envia evidencia de tu perfil profesional construido.'
  UNION ALL
  SELECT
    'Comunicador Digital', 'CC', 'Marketing',
    (SELECT id FROM capacitaciones WHERE titulo = 'Redes sociales como herramienta de difusion educativa' LIMIT 1),
    0,
    'Completa "Redes sociales como herramienta de difusion educativa".'
  UNION ALL
  SELECT
    'Innovador Pedagogico', 'IP', 'Marketing',
    (SELECT id FROM capacitaciones WHERE titulo = 'Marketing digital para promocion institucional' LIMIT 1),
    1,
    'Completa "Marketing digital para promocion institucional" y envia evidencia de una campaña aplicada.'
  UNION ALL
  SELECT
    'Poliglota Certus', 'PC', 'Idiomas',
    (SELECT id FROM capacitaciones WHERE titulo = 'Ingles tecnico para docentes de carreras tecnologicas' LIMIT 1),
    0,
    'Completa "Ingles tecnico para docentes de carreras tecnologicas".'
  UNION ALL
  SELECT
    'Guia Socioemocional', 'GT', 'Psicologia',
    (SELECT id FROM capacitaciones WHERE titulo = 'Salud mental y bienestar docente' LIMIT 1),
    0,
    'Completa "Salud mental y bienestar docente".'
  UNION ALL
  SELECT
    'Educacion Inclusiva', 'EI', 'Psicologia',
    (SELECT id FROM capacitaciones WHERE titulo = 'Acompañamiento socioemocional a estudiantes' LIMIT 1),
    1,
    'Completa "Acompañamiento socioemocional a estudiantes" y envia evidencia de un caso acompañado.'
  UNION ALL
  SELECT
    'Mentor Certus', 'MC', NULL,
    NULL,
    0,
    'Da al menos 10 sesiones de mentoria completadas.'
) AS nuevas
WHERE NOT EXISTS (SELECT 1 FROM insignias);
