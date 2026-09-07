# Guía de backend para el equipo — CERTUS

Esto explica cómo seguir construyendo el backend, usando de ejemplo lo que ya está hecho para **docentes**. La idea es que cada quien tome una tabla y repita el mismo patrón.

## 1. Antes de empezar (una sola vez, cada quien en su compu)

1. Instala XAMPP y prende Apache + MySQL desde su panel de control.
2. Copia la carpeta completa `Proyecto_capacitacion` dentro de `C:\xampp\htdocs\`.
3. Abre `http://localhost/phpmyadmin`, crea la base de datos `certus_db` (si ya la creó otra persona del equipo, no hace falta repetirlo — usen la misma).
4. Corre el archivo `sql/certus_bd.sql` para crear la tabla `docentes` (si todavía no existe).

## 2. El patrón que ya usamos para Docentes (cópienlo tal cual)

Cada entidad (docentes, capacitaciones, mentores...) se construye igual, con 4 piezas:

- **`conexion.php`** — ya existe, un solo archivo compartido por todos los scripts. No hace falta crear uno nuevo por tabla.
- **`insertar_X.php`** (CREATE) — recibe datos en JSON, valida lo obligatorio, y hace `INSERT INTO`.
- **`listar_X.php`** (READ) — hace `SELECT` de todo y devuelve JSON.
- **`actualizar_X.php`** (UPDATE) y **`eliminar_X.php`** (DELETE) — mismo patrón, con `UPDATE ... WHERE id = ...` y `DELETE FROM ... WHERE id = ...`.

Y en el HTML/JS de la pantalla correspondiente, se reemplaza cada lectura del arreglo (`CAPACITACIONES.find(...)`, etc.) por un `fetch()` a esos scripts. Miren `admin/gestion_docentes.html` y `backend/insertar_docente.php` / `backend/listar_docentes.php` como ejemplo concreto ya funcionando.

## 3. Tablas que faltan (repártanselas)

Para cada una: el SQL para crearla, y en qué pantalla(s) se conecta después.

### `administradores` (agregar contraseña real)

```sql
CREATE TABLE administradores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  correo VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  activo TINYINT(1) DEFAULT 1
);
```
Se conecta en: `admin/gestion_administradores.html` (CRUD) y `login.html` (falta conectar el botón "Administrador" a un `login.php` de admin, igual que el de docente).

### `capacitaciones`

```sql
CREATE TABLE capacitaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(150) NOT NULL,
  descripcion TEXT,
  fecha DATE,
  duracion VARCHAR(50),
  estado VARCHAR(30) DEFAULT 'activa',
  sede VARCHAR(100),
  programa VARCHAR(100),
  instructor VARCHAR(100),
  instructor_bio TEXT
);
```
Se conecta en: `admin/gestion_capacitaciones.html` (CRUD), `docente/mis_capacitaciones.html` y `docente/detalle.html` (solo lectura).

### `inscripciones` (relaciona docente + capacitación)

```sql
CREATE TABLE inscripciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_docente INT NOT NULL,
  id_capacitacion INT NOT NULL,
  estado VARCHAR(30) DEFAULT 'pendiente',
  fecha_inscripcion DATE,
  fecha_actualizacion DATE,
  fecha_limite DATE,
  FOREIGN KEY (id_docente) REFERENCES docentes(id),
  FOREIGN KEY (id_capacitacion) REFERENCES capacitaciones(id)
);
```
Depende de que ya existan `docentes` y `capacitaciones`. Se conecta en: `docente/perfil.html`, `docente/mis_capacitaciones.html`, `docente/detalle.html`, `admin/gestion_capacitaciones.html` (botón "Docentes" para asignar), `admin/reportes.html`.

### `constancias`

```sql
CREATE TABLE constancias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_inscripcion INT NOT NULL,
  codigo VARCHAR(50) UNIQUE,
  fecha_emision DATE,
  FOREIGN KEY (id_inscripcion) REFERENCES inscripciones(id)
);
```
Se conecta en: `docente/constancias.html` y `verificar.html`.

### `insignias`

```sql
CREATE TABLE insignias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  icono VARCHAR(20),
  area VARCHAR(100),
  requiere_capacitacion INT,
  requiere_evidencia TINYINT(1) DEFAULT 0,
  requisito VARCHAR(255),
  FOREIGN KEY (requiere_capacitacion) REFERENCES capacitaciones(id)
);
```
Este es el catálogo (son fijas, como las 12 insignias de la demo). Se conecta en: `docente/perfil.html`, `docente/mi_progreso.html`, `admin/gestion_docentes.html` (perfil del docente).

### `evidencias_insignia`

```sql
CREATE TABLE evidencias_insignia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_docente INT NOT NULL,
  id_insignia INT NOT NULL,
  texto TEXT,
  estado VARCHAR(30) DEFAULT 'pendiente',
  fecha DATE,
  FOREIGN KEY (id_docente) REFERENCES docentes(id),
  FOREIGN KEY (id_insignia) REFERENCES insignias(id)
);
```
Se conecta en: `docente/mi_progreso.html` (enviar evidencia) y `admin/gestion_docentes.html` (aprobar/rechazar).

### `mentores`

```sql
CREATE TABLE mentores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombres VARCHAR(100) NOT NULL,
  tema VARCHAR(100),
  rating DECIMAL(2,1) DEFAULT 0,
  sesiones INT DEFAULT 0,
  anios_experiencia INT DEFAULT 0,
  bio TEXT
);
```
Se conecta en: `docente/mentores.html`, `admin/mentores.html`.

### `sesiones_mentoria`

```sql
CREATE TABLE sesiones_mentoria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_docente INT NOT NULL,
  id_mentor INT NOT NULL,
  meta VARCHAR(255),
  horario VARCHAR(50),
  estado VARCHAR(30) DEFAULT 'agendada',
  fecha_creacion DATE,
  FOREIGN KEY (id_docente) REFERENCES docentes(id),
  FOREIGN KEY (id_mentor) REFERENCES mentores(id)
);
```
Se conecta en: `docente/mentores.html`, `docente/perfil.html`, `admin/mentores.html`.

### `autoevaluaciones`

```sql
CREATE TABLE autoevaluaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_docente INT NOT NULL,
  fecha DATE,
  notas TEXT,
  FOREIGN KEY (id_docente) REFERENCES docentes(id)
);
```
Se conecta en: `docente/autoevaluacion.html`.

### `planes_desarrollo` + `metas_plan`

```sql
CREATE TABLE planes_desarrollo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_docente INT NOT NULL,
  periodo VARCHAR(20),
  estado_ciclo VARCHAR(30) DEFAULT 'en progreso',
  FOREIGN KEY (id_docente) REFERENCES docentes(id)
);

CREATE TABLE metas_plan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_plan INT NOT NULL,
  texto VARCHAR(255),
  estado VARCHAR(30) DEFAULT 'en progreso',
  FOREIGN KEY (id_plan) REFERENCES planes_desarrollo(id)
);
```
Un docente tiene un plan por periodo, y cada plan tiene varias metas — por eso son dos tablas. Se conecta en: `docente/mi_progreso.html`, `docente/perfil.html`, `admin/gestion_docentes.html`.

### `sugerencias_capacitacion`

```sql
CREATE TABLE sugerencias_capacitacion (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_docente INT NOT NULL,
  titulo VARCHAR(150),
  detalle TEXT,
  estado VARCHAR(30) DEFAULT 'pendiente',
  fecha DATE,
  FOREIGN KEY (id_docente) REFERENCES docentes(id)
);
```
Se conecta en: `docente/mis_capacitaciones.html` (enviar sugerencia), `admin/gestion_capacitaciones.html` (revisarlas).

## 4. Orden recomendado

No se puede crear una tabla que depende de otra que no existe (por las `FOREIGN KEY`). Sigan este orden:

1. `docentes` (ya está) y `administradores`
2. `capacitaciones`
3. `inscripciones` (necesita las dos anteriores)
4. `constancias`, `insignias`
5. `evidencias_insignia`, `mentores`, `autoevaluaciones`, `planes_desarrollo` + `metas_plan`, `sugerencias_capacitacion`
6. `sesiones_mentoria` (necesita `mentores`)

## 5. Ejemplo completo a copiar (usando `mentores` de referencia)

**`backend/insertar_mentor.php`**
```php
<?php
header("Content-Type: application/json");
require "conexion.php";

$datos = json_decode(file_get_contents("php://input"), true);
$nombres = $conexion->real_escape_string($datos["nombres"] ?? "");
$tema = $conexion->real_escape_string($datos["tema"] ?? "");
$anios_experiencia = intval($datos["anios_experiencia"] ?? 0);
$bio = $conexion->real_escape_string($datos["bio"] ?? "");

if ($nombres === "") {
    echo json_encode(["ok" => false, "mensaje" => "Falta el nombre del mentor."]);
    exit;
}

$sql = "INSERT INTO mentores (nombres, tema, anios_experiencia, bio) VALUES ('$nombres', '$tema', $anios_experiencia, '$bio')";

if ($conexion->query($sql)) {
    echo json_encode(["ok" => true, "id" => $conexion->insert_id]);
} else {
    echo json_encode(["ok" => false, "mensaje" => $conexion->error]);
}
?>
```

**`backend/listar_mentores.php`**
```php
<?php
header("Content-Type: application/json");
require "conexion.php";

$resultado = $conexion->query("SELECT * FROM mentores ORDER BY id DESC");
$mentores = [];
while ($fila = $resultado->fetch_assoc()) {
    $mentores[] = $fila;
}
echo json_encode($mentores);
?>
```

Y en el HTML, igual que hicimos en `gestion_docentes.html`: reemplazar la lectura del arreglo por un `fetch("../backend/listar_mentores.php")` al cargar la página.

## 6. Dudas comunes

- **"No se conecta a la base de datos"** → revisen que Apache y MySQL estén prendidos en XAMPP, y que el nombre de la base de datos en `conexion.php` sea exactamente `certus_db`.
- **"Cambié un archivo y no pasa nada"** → recuerden que `C:\xampp\htdocs\Proyecto_capacitacion` es una copia; hay que volver a copiar el archivo actualizado ahí cada vez.
- **Contraseñas**: siempre con `password_hash()` al guardar y `password_verify()` al comparar — nunca comparen texto plano contra texto plano.
