<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = payload_administrador_request();
$id = id_request_administrador($payload);

if (!obtener_administrador_por_id($conexion, $id)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Administrador no encontrado.'], 404);
}

$errores = [];
$nombres = texto_requerido_administrador($payload, 'nombres', $errores, 100);
$apellidos = texto_requerido_administrador($payload, 'apellidos', $errores, 100);
$correo = texto_requerido_administrador($payload, 'correo', $errores, 150);
$telefono = texto_opcional_administrador($payload, 'telefono', 20);

if ($correo !== '' && !correo_valido_institucional_administrador($correo)) {
    $errores[] = 'El correo debe terminar en @certus.edu.pe.';
}

if ($errores) {
    responder_json(['ok' => false, 'mensaje' => 'Datos invalidos.', 'errores' => $errores], 422);
}

if (correo_duplicado_administrador($conexion, $correo, $id)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya existe un administrador registrado con ese correo.'], 409);
}

$sql = 'UPDATE administradores SET nombres=?, apellidos=?, correo=?, telefono=? WHERE id=?';
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->bind_param('ssssi', $nombres, $apellidos, $correo, $telefono, $id);

if (!$stmt->execute()) {
    $codigoError = (int) $stmt->errno;
    $stmt->close();
    $conexion->close();

    if ($codigoError === 1062) {
        responder_json(['ok' => false, 'mensaje' => 'Ya existe un administrador con ese correo.'], 409);
    }

    responder_json(['ok' => false, 'mensaje' => 'No se pudo actualizar el administrador.'], 500);
}

$stmt->close();

$administrador = obtener_administrador_por_id($conexion, $id);
$conexion->close();

responder_json(['ok' => true, 'mensaje' => 'Administrador actualizado correctamente.', 'administrador' => $administrador]);

?>
