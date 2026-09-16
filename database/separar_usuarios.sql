-- ============================================================
-- Separa la tabla unica "usuarios" (docente/admin mezclados via
-- rol) en dos tablas independientes: "docentes" y "administradores"
-- -- igual que en el diseño original. Migra cualquier fila que ya
-- exista en "usuarios", reapunta las llaves foraneas de todas las
-- tablas que dependian de "usuarios", y al final la elimina.
--
-- Ejecutar UNA sola vez, despues de schema.sql + seed.sql +
-- ampliar_schema.sql.
-- ============================================================

USE capacitacion_db;

CREATE TABLE IF NOT EXISTS docentes (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dni VARCHAR(20) DEFAULT NULL,
  telefono VARCHAR(20) DEFAULT NULL,
  especialidad VARCHAR(150) DEFAULT NULL,
  anios_experiencia INT(11) DEFAULT 0,
  bio TEXT DEFAULT NULL,
  motivo_baja VARCHAR(100) DEFAULT NULL,
  fecha_baja DATE DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS administradores (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  telefono VARCHAR(20) DEFAULT NULL,
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  motivo_baja VARCHAR(100) DEFAULT NULL,
  fecha_baja DATE DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar filas existentes de "usuarios" (si ya habias creado alguna),
-- conservando el mismo id para que las llaves foraneas no se rompan.
INSERT INTO docentes (id, nombres, apellidos, correo, password, activo, fecha_creacion, dni, telefono, especialidad, anios_experiencia, bio, motivo_baja, fecha_baja)
SELECT id, nombres, apellidos, correo, password, activo, fecha_creacion, dni, telefono, especialidad, anios_experiencia, bio, motivo_baja, fecha_baja
FROM usuarios WHERE rol = 'docente';

INSERT INTO administradores (id, nombres, apellidos, correo, password, telefono, activo, fecha_creacion, motivo_baja, fecha_baja)
SELECT id, nombres, apellidos, correo, password, telefono, activo, fecha_creacion, motivo_baja, fecha_baja
FROM usuarios WHERE rol = 'admin';

-- Evitar colisiones de auto_increment con los ids migrados
SET @max_docente_id = (SELECT IFNULL(MAX(id), 0) + 1 FROM docentes);
SET @sql_docentes = CONCAT('ALTER TABLE docentes AUTO_INCREMENT = ', @max_docente_id);
PREPARE stmt_docentes FROM @sql_docentes;
EXECUTE stmt_docentes;
DEALLOCATE PREPARE stmt_docentes;

SET @max_admin_id = (SELECT IFNULL(MAX(id), 0) + 1 FROM administradores);
SET @sql_admins = CONCAT('ALTER TABLE administradores AUTO_INCREMENT = ', @max_admin_id);
PREPARE stmt_admins FROM @sql_admins;
EXECUTE stmt_admins;
DEALLOCATE PREPARE stmt_admins;

-- Reapuntar las llaves foraneas que antes miraban a "usuarios"
ALTER TABLE inscripciones DROP FOREIGN KEY fk_inscripciones_docente;
ALTER TABLE inscripciones ADD CONSTRAINT fk_inscripciones_docente
  FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE solicitudes_capacitacion DROP FOREIGN KEY fk_solicitudes_docente;
ALTER TABLE solicitudes_capacitacion ADD CONSTRAINT fk_solicitudes_docente
  FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE solicitudes_capacitacion DROP FOREIGN KEY fk_solicitudes_resuelto_por;
ALTER TABLE solicitudes_capacitacion ADD CONSTRAINT fk_solicitudes_resuelto_por
  FOREIGN KEY (resuelto_por) REFERENCES administradores (id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE sesiones_mentoria DROP FOREIGN KEY fk_sesiones_docente;
ALTER TABLE sesiones_mentoria ADD CONSTRAINT fk_sesiones_docente
  FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE evidencias_insignia DROP FOREIGN KEY fk_evidencias_docente;
ALTER TABLE evidencias_insignia ADD CONSTRAINT fk_evidencias_docente
  FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE autoevaluaciones DROP FOREIGN KEY fk_autoeval_docente;
ALTER TABLE autoevaluaciones ADD CONSTRAINT fk_autoeval_docente
  FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE planes_desarrollo DROP FOREIGN KEY fk_plan_docente;
ALTER TABLE planes_desarrollo ADD CONSTRAINT fk_plan_docente
  FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE sugerencias_capacitacion DROP FOREIGN KEY fk_sugerencias_docente;
ALTER TABLE sugerencias_capacitacion ADD CONSTRAINT fk_sugerencias_docente
  FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE ciclos_confirmacion DROP FOREIGN KEY fk_ciclo_docente;
ALTER TABLE ciclos_confirmacion ADD CONSTRAINT fk_ciclo_docente
  FOREIGN KEY (id_docente) REFERENCES docentes (id) ON UPDATE CASCADE ON DELETE RESTRICT;

DROP TABLE usuarios;
