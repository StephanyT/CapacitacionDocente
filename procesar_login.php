<?php

session_start();

require_once 'conexion.php';

function redirigir_error_login(): void
{
    header("Location: login.html?error=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir_error_login();
}

$correo = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';
$rol = $_POST['rol'] ?? '';

if (
    $correo === '' ||
    $password === '' ||
    !filter_var($correo, FILTER_VALIDATE_EMAIL) ||
    !in_array($rol, ['docente', 'admin'], true)
) {
    redirigir_error_login();
}

// Docentes y administradores viven en tablas separadas (ya no una sola
// tabla "usuarios" con un campo rol), asi que se consulta la tabla que
// corresponda al rol elegido en el formulario.
$tabla = $rol === 'admin' ? 'administradores' : 'docentes';

$sql = "SELECT id, nombres, apellidos, correo, password
        FROM {$tabla}
        WHERE correo = ? AND activo = 1
        LIMIT 1";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    redirigir_error_login();
}

$stmt->bind_param("s", $correo);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    $stmt->close();
    $conexion->close();
    redirigir_error_login();
}

$usuario = $resultado->fetch_assoc();

if (!password_verify($password, $usuario['password'])) {
    $stmt->close();
    $conexion->close();
    redirigir_error_login();
}

session_regenerate_id(true);

$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['nombre'] = $usuario['nombres'] . ' ' . $usuario['apellidos'];
$_SESSION['correo'] = $usuario['correo'];
$_SESSION['rol'] = $rol;

$destino = $rol === 'admin'
    ? 'admin/dashboard.html'
    : 'docente/perfil.html';

$stmt->close();
$conexion->close();

header("Location: " . $destino);
exit;

?>
