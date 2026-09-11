-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 10-09-2026 a las 01:59:45
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `capacitacion_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `capacitaciones`
--

CREATE TABLE `capacitaciones` (
  `id` int(11) NOT NULL,
  `titulo` varchar(180) NOT NULL,
  `fecha` date NOT NULL,
  `duracion` varchar(50) NOT NULL,
  `sede` varchar(120) NOT NULL,
  `programa` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `instructor` varchar(150) DEFAULT NULL,
  `objetivos` longtext DEFAULT NULL,
  `temario` longtext DEFAULT NULL,
  `habilidades` longtext DEFAULT NULL,
  `instructor_bio` text DEFAULT NULL,
  `requisitos` text DEFAULT NULL,
  `estado` enum('activa','inactiva') NOT NULL DEFAULT 'activa',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `capacitaciones`
--

INSERT INTO `capacitaciones` (`id`, `titulo`, `fecha`, `duracion`, `sede`, `programa`, `descripcion`, `instructor`, `objetivos`, `temario`, `habilidades`, `instructor_bio`, `requisitos`, `estado`, `activo`, `fecha_creacion`, `fecha_actualizacion`) VALUES
(1, 'Estrategias de programacion web en el aula', '2026-09-18', '8 horas', 'Aula Virtual', 'Diseno y Desarrollo de Software', 'Capacitacion orientada a fortalecer la ensenanza de HTML, CSS, JavaScript y buenas practicas de codigo en proyectos formativos.', 'Mg. Carlos Rivera', '[\"Planificar sesiones practicas de programacion web\",\"Aplicar buenas practicas de estructura y validacion\",\"Disenar actividades alineadas a proyectos reales\"]', '[\"Diagnostico de competencias digitales\",\"Estructura de una clase practica\",\"Revision guiada de codigo\",\"Retroalimentacion y cierre\"]', '[\"Programacion web\",\"Didactica digital\",\"Evaluacion practica\"]', 'Especialista en desarrollo web y metodologias activas para carreras tecnologicas.', 'Conocimientos basicos de HTML, CSS y JavaScript.', 'activa', 1, '2026-09-07 05:44:25', '2026-09-07 05:44:25'),
(2, 'Evaluacion por competencias en entornos digitales', '2026-09-25', '6 horas', 'Lima Centro', 'Administracion', 'Taller para disenar rubricas, evidencias y criterios de evaluacion aplicables a clases presenciales y virtuales.', 'Lic. Patricia Salazar', '[\"Construir rubricas claras\",\"Relacionar competencias con evidencias\",\"Usar retroalimentacion formativa\"]', '[\"Resultados de aprendizaje\",\"Criterios e indicadores\",\"Rubricas y evidencias\",\"Retroalimentacion efectiva\"]', '[\"Evaluacion\",\"Rubricas\",\"Retroalimentacion\"]', 'Docente formadora con experiencia en diseno curricular y evaluacion por competencias.', 'Traer una actividad o evaluacion usada actualmente.', 'activa', 1, '2026-09-07 05:44:25', '2026-09-07 05:44:25'),
(3, 'Herramientas de IA para la practica docente', '2026-10-02', '10 horas', 'Aula Virtual', 'Marketing', 'Sesion practica sobre uso responsable de herramientas de IA para preparar materiales, actividades y retroalimentacion docente.', 'Ing. Daniela Medina', '[\"Identificar usos pedagogicos de IA\",\"Crear materiales de apoyo\",\"Aplicar criterios de uso responsable\"]', '[\"Panorama de herramientas\",\"Prompts para docentes\",\"Revision de sesgos\",\"Actividad aplicada\"]', '[\"IA educativa\",\"Diseno de materiales\",\"Uso responsable\"]', 'Consultora en tecnologia educativa y transformacion digital.', 'No requiere experiencia previa con IA.', 'activa', 1, '2026-09-07 05:44:25', '2026-09-07 05:44:25');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inscripciones`
--

CREATE TABLE `inscripciones` (
  `id` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_capacitacion` int(11) NOT NULL,
  `estado` enum('pendiente','en curso','completada') NOT NULL DEFAULT 'pendiente',
  `fecha_inscripcion` date NOT NULL DEFAULT curdate(),
  `fecha_limite` date DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Volcado de datos para la tabla `inscripciones`
--

INSERT INTO `inscripciones` (`id`, `id_docente`, `id_capacitacion`, `estado`, `fecha_inscripcion`, `fecha_limite`, `fecha_actualizacion`) VALUES
(1, 1, 1, 'completada', '2026-09-01', '2026-09-18', '2026-09-18 17:00:00'),
(2, 1, 2, 'en curso', '2026-09-05', '2026-09-25', '2026-09-10 09:00:00'),
(3, 1, 3, 'pendiente', '2026-09-08', '2026-10-02', '2026-09-08 09:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insignias`
--

CREATE TABLE `insignias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `codigo_icono` varchar(20) NOT NULL,
  `requisito` varchar(255) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL,
  `id_capacitacion_requerida` int(11) DEFAULT NULL,
  `requiere_evidencia` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `insignias`
--

INSERT INTO `insignias` (`id`, `nombre`, `codigo_icono`, `requisito`, `area`, `id_capacitacion_requerida`, `requiere_evidencia`, `activo`, `fecha_creacion`) VALUES
(1, 'Programacion web aplicada', 'AD', 'Completar la capacitacion Estrategias de programacion web en el aula.', 'Desarrollo de Software', 1, 0, 1, '2026-09-10 00:00:00'),
(2, 'Evidencia de practica web', 'CC', 'Completar la capacitacion Estrategias de programacion web en el aula y enviar una evidencia aprobada.', 'Desarrollo de Software', 1, 1, 1, '2026-09-10 00:00:00'),
(3, 'IA educativa responsable', 'IA', 'Completar la capacitacion Herramientas de IA para la practica docente.', 'Marketing', 3, 0, 1, '2026-09-10 00:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evidencias_insignia`
--

CREATE TABLE `evidencias_insignia` (
  `id` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_insignia` int(11) NOT NULL,
  `texto` text NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `fecha_envio` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_revision` timestamp NULL DEFAULT NULL,
  `revisado_por` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_capacitacion`
--

CREATE TABLE `solicitudes_capacitacion` (
  `id` int(11) NOT NULL,
  `id_docente` int(11) NOT NULL,
  `id_capacitacion` int(11) NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `fecha_solicitud` date NOT NULL DEFAULT curdate(),
  `fecha_resolucion` date DEFAULT NULL,
  `resuelto_por` int(11) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('docente','admin') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `dni` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `especialidad` varchar(150) DEFAULT NULL,
  `anios_experiencia` int(11) DEFAULT 0,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombres`, `apellidos`, `correo`, `password`, `rol`, `activo`, `fecha_creacion`, `dni`, `telefono`, `especialidad`, `anios_experiencia`, `bio`) VALUES
(1, 'Maria', 'Torres', 'maria.torres@certus.edu.pe', '$2y$10$5KlBl2u/C0dMl5oMZctqu.5GVuVRu0fRh6ptMUVr/z4iJndh/Jkpu', 'docente', 1, '2026-09-07 02:48:45', '12345678', '987654321', 'Desarrollo de Software', 5, 'Docente del area de Desarrollo de Software, enfocada en programacion web y buenas practicas de codigo.'),
(9, 'Administrador', 'Certus', 'administrador.certus@certus.edu.pe', '$2y$10$TAuLcLSQcUqxR3qenGzLsOg8najQXJLbLNGsaMXFMfF74IuhSB9w6', 'admin', 1, '2026-09-09 23:29:38', NULL, NULL, NULL, 0, NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `capacitaciones`
--
ALTER TABLE `capacitaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_capacitaciones_activo` (`activo`),
  ADD KEY `idx_capacitaciones_programa` (`programa`),
  ADD KEY `idx_capacitaciones_fecha` (`fecha`);

--
-- Indices de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inscripciones_docente_capacitacion` (`id_docente`,`id_capacitacion`),
  ADD KEY `idx_inscripciones_docente` (`id_docente`),
  ADD KEY `idx_inscripciones_capacitacion` (`id_capacitacion`),
  ADD KEY `idx_inscripciones_estado` (`estado`),
  ADD KEY `idx_inscripciones_fecha_limite` (`fecha_limite`);

--
-- Indices de la tabla `insignias`
--
ALTER TABLE `insignias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_insignias_capacitacion` (`id_capacitacion_requerida`),
  ADD KEY `idx_insignias_activo` (`activo`);

--
-- Indices de la tabla `evidencias_insignia`
--
ALTER TABLE `evidencias_insignia`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_evidencias_docente` (`id_docente`),
  ADD KEY `idx_evidencias_insignia` (`id_insignia`),
  ADD KEY `idx_evidencias_estado` (`estado`),
  ADD KEY `idx_evidencias_revisado_por` (`revisado_por`);

--
-- Indices de la tabla `solicitudes_capacitacion`
--
ALTER TABLE `solicitudes_capacitacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_solicitudes_docente_capacitacion` (`id_docente`,`id_capacitacion`),
  ADD KEY `idx_solicitudes_docente` (`id_docente`),
  ADD KEY `idx_solicitudes_capacitacion` (`id_capacitacion`),
  ADD KEY `idx_solicitudes_estado` (`estado`),
  ADD KEY `idx_solicitudes_resuelto_por` (`resuelto_por`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `capacitaciones`
--
ALTER TABLE `capacitaciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `insignias`
--
ALTER TABLE `insignias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `evidencias_insignia`
--
ALTER TABLE `evidencias_insignia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitudes_capacitacion`
--
ALTER TABLE `solicitudes_capacitacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `inscripciones`
--
ALTER TABLE `inscripciones`
  ADD CONSTRAINT `fk_inscripciones_capacitacion` FOREIGN KEY (`id_capacitacion`) REFERENCES `capacitaciones` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inscripciones_docente` FOREIGN KEY (`id_docente`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `insignias`
--
ALTER TABLE `insignias`
  ADD CONSTRAINT `fk_insignias_capacitacion` FOREIGN KEY (`id_capacitacion_requerida`) REFERENCES `capacitaciones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `evidencias_insignia`
--
ALTER TABLE `evidencias_insignia`
  ADD CONSTRAINT `fk_evidencias_docente` FOREIGN KEY (`id_docente`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evidencias_insignia` FOREIGN KEY (`id_insignia`) REFERENCES `insignias` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_evidencias_revisado_por` FOREIGN KEY (`revisado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitudes_capacitacion`
--
ALTER TABLE `solicitudes_capacitacion`
  ADD CONSTRAINT `fk_solicitudes_capacitacion` FOREIGN KEY (`id_capacitacion`) REFERENCES `capacitaciones` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitudes_docente` FOREIGN KEY (`id_docente`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_solicitudes_resuelto_por` FOREIGN KEY (`resuelto_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
