CREATE DATABASE IF NOT EXISTS capacitacion_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE capacitacion_db;

CREATE TABLE IF NOT EXISTS usuarios (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL,
  password VARCHAR(255) NOT NULL,
  rol ENUM('docente', 'admin') NOT NULL,
  activo TINYINT(1) DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dni VARCHAR(20) DEFAULT NULL,
  telefono VARCHAR(20) DEFAULT NULL,
  especialidad VARCHAR(150) DEFAULT NULL,
  anios_experiencia INT(11) DEFAULT 0,
  bio TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS capacitaciones (
  id INT(11) NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(180) NOT NULL,
  fecha DATE NOT NULL,
  duracion VARCHAR(50) NOT NULL,
  sede VARCHAR(120) NOT NULL,
  programa VARCHAR(150) NOT NULL,
  descripcion TEXT NOT NULL,
  instructor VARCHAR(150) DEFAULT NULL,
  objetivos LONGTEXT DEFAULT NULL,
  temario LONGTEXT DEFAULT NULL,
  habilidades LONGTEXT DEFAULT NULL,
  instructor_bio TEXT DEFAULT NULL,
  requisitos TEXT DEFAULT NULL,
  estado ENUM('activa', 'inactiva') NOT NULL DEFAULT 'activa',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_capacitaciones_activo (activo),
  KEY idx_capacitaciones_programa (programa),
  KEY idx_capacitaciones_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inscripciones (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  id_capacitacion INT(11) NOT NULL,
  estado ENUM('pendiente', 'en curso', 'completada') NOT NULL DEFAULT 'pendiente',
  fecha_inscripcion DATE NOT NULL DEFAULT (CURRENT_DATE),
  fecha_limite DATE DEFAULT NULL,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_inscripciones_docente_capacitacion (id_docente, id_capacitacion),
  KEY idx_inscripciones_docente (id_docente),
  KEY idx_inscripciones_capacitacion (id_capacitacion),
  KEY idx_inscripciones_estado (estado),
  KEY idx_inscripciones_fecha_limite (fecha_limite),
  CONSTRAINT fk_inscripciones_docente
    FOREIGN KEY (id_docente) REFERENCES usuarios (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_inscripciones_capacitacion
    FOREIGN KEY (id_capacitacion) REFERENCES capacitaciones (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_inscripciones_estado
    CHECK (estado IN ('pendiente', 'en curso', 'completada'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS insignias (
  id INT(11) NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  codigo_icono VARCHAR(20) NOT NULL,
  requisito VARCHAR(255) DEFAULT NULL,
  area VARCHAR(100) DEFAULT NULL,
  id_capacitacion_requerida INT(11) DEFAULT NULL,
  requiere_evidencia TINYINT(1) NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_insignias_capacitacion (id_capacitacion_requerida),
  KEY idx_insignias_activo (activo),
  CONSTRAINT fk_insignias_capacitacion
    FOREIGN KEY (id_capacitacion_requerida) REFERENCES capacitaciones (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evidencias_insignia (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  id_insignia INT(11) NOT NULL,
  texto TEXT NOT NULL,
  estado ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
  fecha_envio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_revision TIMESTAMP NULL DEFAULT NULL,
  revisado_por INT(11) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_evidencias_docente (id_docente),
  KEY idx_evidencias_insignia (id_insignia),
  KEY idx_evidencias_estado (estado),
  KEY idx_evidencias_revisado_por (revisado_por),
  CONSTRAINT fk_evidencias_docente
    FOREIGN KEY (id_docente) REFERENCES usuarios (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_evidencias_insignia
    FOREIGN KEY (id_insignia) REFERENCES insignias (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_evidencias_revisado_por
    FOREIGN KEY (revisado_por) REFERENCES usuarios (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT chk_evidencias_estado
    CHECK (estado IN ('pendiente', 'aprobada', 'rechazada'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitudes_capacitacion (
  id INT(11) NOT NULL AUTO_INCREMENT,
  id_docente INT(11) NOT NULL,
  id_capacitacion INT(11) NOT NULL,
  estado ENUM('pendiente', 'aprobada', 'rechazada') NOT NULL DEFAULT 'pendiente',
  fecha_solicitud DATE NOT NULL DEFAULT (CURRENT_DATE),
  fecha_resolucion DATE DEFAULT NULL,
  resuelto_por INT(11) DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_solicitudes_docente_capacitacion (id_docente, id_capacitacion),
  KEY idx_solicitudes_docente (id_docente),
  KEY idx_solicitudes_capacitacion (id_capacitacion),
  KEY idx_solicitudes_estado (estado),
  KEY idx_solicitudes_resuelto_por (resuelto_por),
  CONSTRAINT fk_solicitudes_docente
    FOREIGN KEY (id_docente) REFERENCES usuarios (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_solicitudes_capacitacion
    FOREIGN KEY (id_capacitacion) REFERENCES capacitaciones (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_solicitudes_resuelto_por
    FOREIGN KEY (resuelto_por) REFERENCES usuarios (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT chk_solicitudes_estado
    CHECK (estado IN ('pendiente', 'aprobada', 'rechazada'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
