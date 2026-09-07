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
