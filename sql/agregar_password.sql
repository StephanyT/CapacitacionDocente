-- Ya tienes la tabla docentes creada sin columna de contrasena.
-- Corre esto UNA VEZ en phpMyAdmin (pestana "SQL") para agregarla,
-- en vez de volver a crear la tabla desde cero.

ALTER TABLE docentes ADD COLUMN password VARCHAR(255) NOT NULL AFTER correo;
