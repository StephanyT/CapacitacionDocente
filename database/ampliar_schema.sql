-- ============================================================
-- Ampliacion del esquema de Brandon (capacitacion_db) para cubrir
-- los modulos que faltaban: constancias, administradores/docentes
-- completos, mentores, insignias, autoevaluacion, plan de
-- desarrollo, sugerencias y ciclo de actualizacion profesional.
-- Ejecutar UNA sola vez en phpMyAdmin, despues de schema.sql.
-- ============================================================

USE capacitacion_db;

-- Motivo/fecha de baja para docentes Y administradores (ambos viven en usuarios)
ALTER TABLE usuarios
  ADD COLUMN motivo_baja VARCHAR(100) DEFAULT NULL,
  ADD COLUMN fecha_baja DATE DEFAULT NULL;

-- ===== Constancias (RF-13/RF-14) =====
CREATE TABLE IF NOT EXISTS constancias (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_inscripcion INT(11) NOT NULL,
  codigo VARCHAR(40) DEFAULT NULL,
  fecha_emision DATE NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (id),
  UNIQUE KEY uq_constancias_inscripcion (id_inscripcion),
  UNIQUE KEY uq_constancias_codigo (codigo),
  CONSTRAINT fk_constancias_inscripcion
    FOREIGN KEY (id_inscripcion) REFERENCES inscripciones (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Mentores + sesiones + reseñas =====
CREATE TABLE IF NOT EXISTS mentores (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombres VARCHAR(150) NOT NULL,
  tema VARCHAR(150) NOT NULL,
  bio TEXT DEFAULT NULL,
  rating DECIMAL(2,1) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sesiones_mentoria (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  id_mentor INT(11) NOT NULL,
  fecha DATE NOT NULL,
  meta VARCHAR(255) DEFAULT NULL,
  estado ENUM('agendada','completada','cancelada') NOT NULL DEFAULT 'agendada',
  resenia_enviada TINYINT(1) NOT NULL DEFAULT 0,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sesiones_docente (id_docente),
  KEY idx_sesiones_mentor (id_mentor),
  CONSTRAINT fk_sesiones_docente FOREIGN KEY (id_docente) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_sesiones_mentor FOREIGN KEY (id_mentor) REFERENCES mentores (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS resenas_mentoria (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_mentor INT(11) NOT NULL,
  id_sesion INT(11) DEFAULT NULL,
  autor VARCHAR(150) NOT NULL,
  comentario TEXT DEFAULT NULL,
  rating TINYINT(1) NOT NULL,
  fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_resenas_mentor (id_mentor),
  CONSTRAINT fk_resenas_mentor FOREIGN KEY (id_mentor) REFERENCES mentores (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Insignias + evidencias =====
CREATE TABLE IF NOT EXISTS insignias (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  icono VARCHAR(20) DEFAULT NULL,
  area VARCHAR(100) DEFAULT NULL,
  requiere_capacitacion INT(11) DEFAULT NULL,
  evidencia TINYINT(1) NOT NULL DEFAULT 0,
  requisito VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidencias_insignia (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  id_insignia INT(11) NOT NULL,
  texto TEXT DEFAULT NULL,
  estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  fecha DATE DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_evidencias_docente (id_docente),
  KEY idx_evidencias_insignia (id_insignia),
  CONSTRAINT fk_evidencias_docente FOREIGN KEY (id_docente) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_evidencias_insignia FOREIGN KEY (id_insignia) REFERENCES insignias (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Autoevaluacion con video + comentarios colaborativos =====
CREATE TABLE IF NOT EXISTS autoevaluaciones (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  id_capacitacion INT(11) NOT NULL,
  video_link VARCHAR(255) DEFAULT NULL,
  checklist VARCHAR(20) DEFAULT NULL,
  nota_propia TEXT DEFAULT NULL,
  fecha DATE NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (id),
  KEY idx_autoeval_docente (id_docente),
  CONSTRAINT fk_autoeval_docente FOREIGN KEY (id_docente) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_autoeval_capacitacion FOREIGN KEY (id_capacitacion) REFERENCES capacitaciones (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comentarios_autoevaluacion (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_autoevaluacion INT(11) NOT NULL,
  autor VARCHAR(150) NOT NULL,
  texto TEXT NOT NULL,
  fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_comentarios_autoeval (id_autoevaluacion),
  CONSTRAINT fk_comentarios_autoeval FOREIGN KEY (id_autoevaluacion) REFERENCES autoevaluaciones (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Plan de desarrollo profesional (IPDP) =====
CREATE TABLE IF NOT EXISTS planes_desarrollo (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  periodo VARCHAR(20) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plan_docente_periodo (id_docente, periodo),
  CONSTRAINT fk_plan_docente FOREIGN KEY (id_docente) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS metas_desarrollo (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_plan INT(11) NOT NULL,
  texto VARCHAR(255) NOT NULL,
  estado ENUM('en progreso','cumplida') NOT NULL DEFAULT 'en progreso',
  PRIMARY KEY (id),
  KEY idx_metas_plan (id_plan),
  CONSTRAINT fk_metas_plan FOREIGN KEY (id_plan) REFERENCES planes_desarrollo (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Sugerencias de tema (needs assessment) =====
CREATE TABLE IF NOT EXISTS sugerencias_capacitacion (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  titulo VARCHAR(180) NOT NULL,
  detalle TEXT DEFAULT NULL,
  estado ENUM('pendiente','atendida') NOT NULL DEFAULT 'pendiente',
  fecha DATE NOT NULL DEFAULT (CURRENT_DATE),
  PRIMARY KEY (id),
  KEY idx_sugerencias_docente (id_docente),
  CONSTRAINT fk_sugerencias_docente FOREIGN KEY (id_docente) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===== Ciclo de actualizacion profesional (MyGTCS) =====
CREATE TABLE IF NOT EXISTS ciclos_confirmacion (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  periodo VARCHAR(20) NOT NULL,
  estado ENUM('solicitado','confirmado') NOT NULL DEFAULT 'solicitado',
  fecha_solicitud DATE DEFAULT NULL,
  confirmado_por VARCHAR(150) DEFAULT NULL,
  fecha_confirmacion DATE DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ciclo_docente_periodo (id_docente, periodo),
  CONSTRAINT fk_ciclo_docente FOREIGN KEY (id_docente) REFERENCES usuarios (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
