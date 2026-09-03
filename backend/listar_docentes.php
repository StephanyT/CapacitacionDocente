<?php
// READ: devuelve todos los docentes guardados en la base de datos.
header("Content-Type: application/json");
require "conexion.php";

$resultado = $conexion->query("SELECT id, nombres, apellidos, correo, dni, telefono, especialidad, anios_experiencia, bio, activo FROM docentes ORDER BY id DESC");

$docentes = [];
while ($fila = $resultado->fetch_assoc()) {
    $fila["id"] = intval($fila["id"]);
    $fila["anios_experiencia"] = intval($fila["anios_experiencia"]);
    $fila["activo"] = intval($fila["activo"]);
    $docentes[] = $fila;
}

echo json_encode($docentes);
$conexion->close();
?>
