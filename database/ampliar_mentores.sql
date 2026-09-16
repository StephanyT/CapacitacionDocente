USE capacitacion_db;

-- Completa las tablas de mentoria creadas en ampliar_schema.sql (que se
-- quedaron sin usar): liga cada mentor a un docente real (en vez de
-- guardar su nombre suelto y adivinar la relacion por texto en JS),
-- agrega disponibilidad de horarios y un estado activo/inactivo, y agrega
-- el horario elegido (slot) a cada sesion agendada.

ALTER TABLE mentores
  ADD COLUMN id_docente INT(11) NULL AFTER id,
  ADD COLUMN disponibilidad TEXT NULL AFTER bio,
  ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER disponibilidad,
  ADD CONSTRAINT fk_mentores_docente FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE sesiones_mentoria
  ADD COLUMN slot VARCHAR(100) NULL AFTER id_mentor;
