# CERTUS — Gestión Docente

Prototipo de aplicación web responsiva para automatizar la gestión del legajo docente de la Escuela de Educación Superior CERTUS: capacitaciones, seguimiento de progreso, constancias y reportes consolidados.

Proyecto integrador — Experiencias Formativas en Situaciones Reales de Trabajo 1 (EFSRT 1), Diseño y Desarrollo de Software.

## Stack

- **Backend:** PHP + MySQL (mysqli), sesiones de PHP, contraseñas con `password_hash()` / `password_verify()`.
- **Frontend:** HTML, CSS y JavaScript vanilla (sin frameworks), diseño responsivo.
- **Entorno de desarrollo:** XAMPP (Apache + MySQL).

## Roles

- **Docente:** visualiza sus capacitaciones asignadas y disponibles, actualiza su perfil, descarga constancias, y accede a las funcionalidades de valor agregado (mentores, insignias, autoevaluación, plan de desarrollo).
- **Administrador:** gestiona docentes, administradores, capacitaciones, asignaciones y reportes consolidados.

Ambos roles inician sesión con correo institucional y contraseña contra tablas separadas (`docentes` / `administradores`).

## Alcance

### Requerimientos base (Documento de Requerimientos, RF-01 a RF-18)

Autenticación, gestión de usuarios (docentes/administradores), gestión de capacitaciones, asignación y seguimiento de progreso, generación y descarga de constancias, y reportes con filtros y exportación.

### Funcionalidades de innovación (fuera del documento oficial, valor agregado del equipo)

Mentores y sesiones de mentoría con reseñas, insignias con evidencias, autoevaluación docente con video y comentarios de colegas, plan de desarrollo profesional con metas, sugerencias de capacitación y ciclos de confirmación de datos.

### Explícitamente fuera de alcance

Integración con sistemas institucionales existentes de CERTUS, despliegue en producción, aplicación móvil nativa, firma digital avanzada de constancias, y módulos administrativos ajenos a capacitación (planillas, pagos, logística).

## Estructura de carpetas

```
certus/
├── admin/          Páginas del panel de administrador
├── docente/        Páginas del panel de docente
├── api/            Endpoints PHP (uno por acción, agrupados por entidad)
├── database/       Esquema SQL
├── js/             app.js (lógica compartida) y data.js (arreglos en memoria)
├── css/            theme.css (hoja de estilos única)
├── img/            Recursos gráficos
├── login.html      Inicio de sesión
├── verificar.html  Verificación pública de constancias por código/QR
└── *.php sueltos   auth.php, conexion.php, logout.php, procesar_login.php, sesion.php, obtener_docente.php
```

## Instalación local (XAMPP)

1. Copia esta carpeta completa a `C:\xampp\htdocs\certus`.
2. Importa `database/capacitacion_db.sql` en MySQL (phpMyAdmin) como base de datos `capacitacion_db`.
3. Ajusta las credenciales de conexión en `conexion.php` si es necesario.
4. Inicia Apache y MySQL desde el Panel de Control de XAMPP.
5. Abre `http://localhost/certus/login.html`.

## Credenciales de prueba

- **Docente:** correo del docente + su DNI como contraseña.
- **Administrador:** el botón de acceso rápido en `login.html` permite entrar sin credenciales, pensado para que cualquiera pueda probar la app sin depender de un usuario específico.

## Licencia / contexto

Proyecto académico, prototipo funcional sin fines de producción.
