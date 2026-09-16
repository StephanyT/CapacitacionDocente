<?php

require_once 'auth.php';
require_once 'conexion.php';

$usuario = exigir_rol('docente', true);
$id = $usuario['id'];

$sql = "SELECT id, nombres, apellidos, correo, dni, telefono,
               especialidad, anios_experiencia, bio
        FROM docentes
        WHERE id = ? AND activo = 1";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json([
        "ok" => false,
        "mensaje" => "No se pudo preparar la consulta."
    ], 500);
}

$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 1) {

    $docente = $resultado->fetch_assoc();

    $stmt->close();
    $conexion->close();

    responder_json([
        "ok" => true,
        "docente" => $docente
    ]);

}

$stmt->close();
$conexion->close();

responder_json([
    "ok" => false,
    "mensaje" => "Docente no encontrado."
], 404);

?>
