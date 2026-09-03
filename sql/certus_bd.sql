-- Base de datos CERTUS: primera tabla (docentes)
-- Ejecutar esto en phpMyAdmin -> pestana "SQL", con la base de datos certus_bd
-- ya creada y seleccionada.

CREATE TABLE docentes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  dni VARCHAR(20) NOT NULL UNIQUE,
  telefono VARCHAR(20),
  especialidad VARCHAR(100),
  anios_experiencia INT DEFAULT 0,
  bio TEXT,
  activo TINYINT(1) DEFAULT 1
);
