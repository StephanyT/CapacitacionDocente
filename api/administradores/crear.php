<?php

require_once __DIR__ . '/_helpers.php';

exigir_rol('admin', true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'mensaje' => 'Metodo no permitido.'], 405);
}

$payload = payload_administrador_request();
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

if (correo_duplicado_administrador($conexion, $correo, null)) {
    $conexion->close();
    responder_json(['ok' => false, 'mensaje' => 'Ya existe un administrador registrado con ese correo.'], 409);
}

// No hay campo de contrasena en el formulario (RF-03/04/05): se genera una
// temporal y se devuelve una sola vez en la respuesta para que el admin que
// crea la cuenta se la comparta al nuevo administrador.
$passwordTemporal = generar_password_temporal_administrador();
$passwordHash = password_hash($passwordTemporal, PASSWORD_DEFAULT);

$sql = 'INSERT INTO administradores (nombres, apellidos, correo, password, activo, telefono) VALUES (?, ?, ?, ?, 1, ?)';
$stmt = $conexion->prepare($sql);

if (!$stmt) {
    responder_json(['ok' => false, 'mensaje' => 'No se pudo preparar la consulta.'], 500);
}

$stmt->bind_param('sssss', $nombres, $apellidos, $correo, $passwordHash, $telefono);
$stmt->execute();

if ($stmt->errno) {
    $codigoError = (int) $stmt->errno;
    $stmt->close();
    $conexion->close();

    if ($codigoError === 1062) {
        responder_json(['ok' => false, 'mensaje' => 'Ya existe un administrador con ese correo.'], 409);
    }

    responder_json(['ok' => false, 'mensaje' => 'No se pudo crear el administrador.'], 500);
}

$idNuevo = (int) $conexion->insert_id;
$stmt->close();

$administrador = obtener_administrador_por_id($conexion, $idNuevo);
$conexion->close();

responder_json([
    'ok' => true,
    'mensaje' => "Administrador creado. Contrasena temporal: {$passwordTemporal}",
    'password_temporal' => $passwordTemporal,
    'administrador' => $administrador
]);

?>
