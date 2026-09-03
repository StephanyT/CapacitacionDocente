<?php
// CREATE: registra un nuevo docente en la base de datos.
// Recibe los datos del formulario en formato JSON (via fetch desde el frontend).
header("Content-Type: application/json");
require "conexion.php";

$datos = json_decode(file_get_contents("php://input"), true);

$nombres = $conexion->real_escape_string($datos["nombres"] ?? "");
$apellidos = $conexion->real_escape_string($datos["apellidos"] ?? "");
$correo = $conexion->real_escape_string($datos["correo"] ?? "");
$claveTexto = $datos["password"] ?? "";
$dni = $conexion->real_escape_string($datos["dni"] ?? "");
$telefono = $conexion->real_escape_string($datos["telefono"] ?? "");
$especialidad = $conexion->real_escape_string($datos["especialidad"] ?? "");
$anios_experiencia = intval($datos["anios_experiencia"] ?? 0);
$bio = $conexion->real_escape_string($datos["bio"] ?? "");

if ($nombres === "" || $apellidos === "" || $correo === "" || $dni === "") {
    echo json_encode(["ok" => false, "mensaje" => "Faltan datos obligatorios (nombres, apellidos, correo o DNI)."]);
    exit;
}

if (strlen($claveTexto) < 6) {
    echo json_encode(["ok" => false, "mensaje" => "La contrasena debe tener al menos 6 caracteres."]);
    exit;
}

// Nunca se guarda la contrasena tal cual la escribio el docente -- se guarda
// un hash (password_hash), que no se puede leer ni revertir a la contrasena
// original. Al iniciar sesion se compara con password_verify().
$password = $conexion->real_escape_string(password_hash($claveTexto, PASSWORD_DEFAULT));

$sql = "INSERT INTO docentes (nombres, apellidos, correo, password, dni, telefono, especialidad, anios_experiencia, bio)
        VALUES ('$nombres', '$apellidos', '$correo', '$password', '$dni', '$telefono', '$especialidad', $anios_experiencia, '$bio')";

if ($conexion->query($sql)) {
    echo json_encode(["ok" => true, "mensaje" => "Docente registrado correctamente", "id" => $conexion->insert_id]);
} else {
    echo json_encode(["ok" => false, "mensaje" => "Error al guardar: " . $conexion->error]);
}

$conexion->close();
?>
