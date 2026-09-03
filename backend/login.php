<?php
// Verifica correo + contrasena contra la tabla docentes.
header("Content-Type: application/json");
require "conexion.php";

$datos = json_decode(file_get_contents("php://input"), true);
$correo = $conexion->real_escape_string($datos["correo"] ?? "");
$claveTexto = $datos["password"] ?? "";

if ($correo === "" || $claveTexto === "") {
    echo json_encode(["ok" => false, "mensaje" => "Ingresa correo y contrasena."]);
    exit;
}

$resultado = $conexion->query("SELECT id, nombres, apellidos, password, activo FROM docentes WHERE correo = '$correo' LIMIT 1");

if ($resultado->num_rows === 0) {
    echo json_encode(["ok" => false, "mensaje" => "Credenciales invalidas."]);
    exit;
}

$docente = $resultado->fetch_assoc();

if (!intval($docente["activo"])) {
    echo json_encode(["ok" => false, "mensaje" => "Esta cuenta fue desactivada. Contacta al administrador."]);
    exit;
}

// password_verify compara la contrasena escrita contra el hash guardado --
// nunca se compara texto plano contra texto plano.
if (!password_verify($claveTexto, $docente["password"])) {
    echo json_encode(["ok" => false, "mensaje" => "Credenciales invalidas."]);
    exit;
}

echo json_encode([
    "ok" => true,
    "rol" => "docente",
    "id" => intval($docente["id"]),
    "nombre" => $docente["nombres"] . " " . $docente["apellidos"]
]);

$conexion->close();
?>
